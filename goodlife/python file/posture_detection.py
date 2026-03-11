import cv2
import mediapipe as mp
import numpy as np
import sounddevice as sd
import vosk
import queue
import json
import threading
import time
import math
import os
import signal
import requests
from datetime import datetime
from flask import Flask, Response, jsonify, request
from flask_cors import CORS

# Set working directory to script location
os.chdir(os.path.dirname(os.path.abspath(__file__)))

# ===================
# CONFIGURATION
# ===================
WEBHOOK_BASE_URL = "http://localhost/goodlife/" 

app = Flask(__name__)
CORS(app) 

# ===================
# GLOBAL STATE
# ===================
system_state = {
    "locked": False,
    "message": "",
    "mode": "NORMAL"
}

# Session Data
current_elder_id = None
session_start_time = None
session_video_path_web = None
session_video_path_local = None
session_writer = None

# State Machine: "NORMAL", "GRACE", "RECORDING", "LOCKED"
machine_state = "NORMAL"
current_event_id = None
grace_start_time = None
record_start_time = None
event_writer = None
event_video_path_web = None
current_alert_types = []

# Persistent Processing Flags
latest_frame = None
is_monitoring = False
exit_flag = False  # For graceful termination
monitor_thread = None

# ===================
# SENSITIVITY SETTINGS
# ===================
RELATIVE_SLUMP_THRESHOLD = 0.15
TILT_THRESHOLD_DEG = 45
EYE_SQUINT_THRESHOLD = 0.02
HAND_HOLD_THRESHOLD = 1.5
SLUMP_HOLD_DURATION = 2.0  
TILT_HOLD_DURATION = 1.5    
PAIN_HOLD_DURATION = 2.0    

# ===================
# AUDIO SETUP
# ===================
audio_stream = None
try:
    model = vosk.Model("vosk-model-small-en-us-0.15")
    audio_q = queue.Queue()
    voice_alert = False
    keywords = ["help", "ah", "ahh", "ouch", "ow", "pain", "emergency", "stop", "doctor", "hurt"]

    def audio_callback(indata, frames, time_info, status):
        audio_q.put(bytes(indata))

    def speech_worker():
        global voice_alert
        rec = vosk.KaldiRecognizer(model, 16000)
        while not exit_flag:
            try:
                data = audio_q.get(timeout=1)
                if rec.AcceptWaveform(data):
                    result = json.loads(rec.Result())
                    text = result.get("text", "")
                    if text:
                        print("Recognized:", text)
                        for word in keywords:
                            if word in text.lower():
                                voice_alert = True
            except queue.Empty:
                continue
            except Exception as e:
                print(f"Speech worker error: {e}")
                break

    audio_stream = sd.RawInputStream(samplerate=16000, blocksize=8000, dtype='int16', channels=1, callback=audio_callback)
    audio_stream.start()
    threading.Thread(target=speech_worker, daemon=True).start()
    print("Vosk Audio System Initialized.")
except Exception as e:
    print(f"Error initializing audio: {e}")

# ===================
# MEDIAPIPE SETUP
# ===================
print("Initializing Mediapipe...")
try:
    mp_face = mp.solutions.face_mesh
    face_mesh = mp_face.FaceMesh(max_num_faces=1, refine_landmarks=True)
    mp_pose = mp.solutions.pose
    pose = mp_pose.Pose(min_detection_confidence=0.5, min_tracking_confidence=0.5)
    mp_hands = mp.solutions.hands
    hands = mp_hands.Hands(max_num_hands=1, min_detection_confidence=0.7)
    print("Mediapipe Initialized.")
except Exception as e:
    print(f"Error initializing Mediapipe: {e}")
    exit(1)

# Helpers
def dist(a, b): return np.linalg.norm(np.array(a) - np.array(b))
def p(lm, idx, w, h): return (int(lm[idx].x * w), int(lm[idx].y * h))
def get_coords(landmarks, idx, w, h):
    if landmarks[idx].visibility < 0.2: return None
    return np.array([landmarks[idx].x * w, landmarks[idx].y * h])
def calculate_tilt(p1, p2):
    dy = p2[1] - p1[1]
    dx = p2[0] - p1[0]
    return abs(math.degrees(math.atan2(dy, dx)))

def draw_progress_bar(frame, x, y, start_time, duration, color, label):
    if start_time is None: return False
    elapsed = time.time() - start_time
    progress = min(elapsed / duration, 1.0)
    bar_w, bar_h = 100, 10
    x = max(0, min(x, frame.shape[1] - bar_w))
    y = max(20, min(y, frame.shape[0] - bar_h))
    cv2.rectangle(frame, (x, y), (x + bar_w, y + bar_h), (50, 50, 50), -1)
    cv2.rectangle(frame, (x, y), (x + int(bar_w * progress), y + bar_h), color, -1)
    cv2.putText(frame, f"{label} {int(progress*100)}%", (x, y - 5), cv2.FONT_HERSHEY_PLAIN, 1, color, 1)
    return progress >= 1.0

def is_thumbs_up(hand_landmarks):
    lm = hand_landmarks.landmark
    def get_dist(idx1, idx2): return math.hypot(lm[idx1].x - lm[idx2].x, lm[idx1].y - lm[idx2].y)
    thumb_is_up = (lm[4].y < lm[3].y) and (lm[4].y < lm[5].y) and (get_dist(4, 0) > get_dist(3, 0))
    fingers_curled = True
    for tip, pip in [(8, 6), (12, 10), (16, 14), (20, 18)]:
        if get_dist(tip, 0) > get_dist(pip, 0) + 0.02:
            fingers_curled = False
            break
    return thumb_is_up and fingers_curled

# ===================
# MONITORING THREAD
# ===================
def monitoring_loop():
    global voice_alert, system_state, machine_state
    global session_writer, event_writer, event_video_path
    global grace_start_time, record_start_time, current_alert_types
    global latest_frame, is_monitoring, exit_flag, current_event_id

    print("Opening camera...")
    cap = cv2.VideoCapture(0, cv2.CAP_DSHOW)
    if not cap.isOpened(): cap = cv2.VideoCapture(0)
    
    if not cap.isOpened():
        print("CRITICAL: Camera failed.")
        is_monitoring = False
        return

    # Configuration for standard 640x480
    cap.set(cv2.CAP_PROP_FRAME_WIDTH, 640)
    cap.set(cv2.CAP_PROP_FRAME_HEIGHT, 480)

    try:
        ret, frame = cap.read()
        if ret:
            h, w, _ = frame.shape
            os.makedirs("../uploads/videos", exist_ok=True)
            if current_elder_id and session_writer is None:
                fourcc = cv2.VideoWriter_fourcc(*'VP80') 
                session_writer = cv2.VideoWriter(session_video_path_local, fourcc, 20.0, (w, h))
        
        last_voice_time = 0
        hand_enter_time = None
        slump_start_time = None
        tilt_start_time = None
        pain_start_time = None

        print("Loop started.")

        while not exit_flag and cap.isOpened():
            ret, frame = cap.read()
            if not ret: break

            h, w, _ = frame.shape
            rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            
            if session_writer is not None:
                session_writer.write(frame)

            # AI Logic (Locked, Recording, Grace, Normal states)
            if machine_state == "LOCKED":
                system_state["locked"] = True
                system_state["message"] = " + ".join(current_alert_types)
                overlay = frame.copy()
                cv2.rectangle(overlay, (0, 0), (w, h), (0, 0, 150), -1)
                frame = cv2.addWeighted(overlay, 0.4, frame, 0.6, 0)
                cv2.putText(frame, f"ALERT: {system_state['message']}", (50, h//2 - 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 3)
                cv2.putText(frame, "WAITING FOR CAREGIVER...", (50, h//2 + 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 255), 2)
                # THUMBS UP REMOVED - MUST BE RESOLVED BY CAREGIVER

            elif machine_state == "RECORDING":
                system_state["locked"] = True
                system_state["message"] = "RECORDING EVENT..."
                cv2.rectangle(frame, (0, 0), (w, h), (0, 0, 255), 4)
                cv2.putText(frame, "RECORDING ALERT...", (50, 50), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 0, 255), 3)
                if event_writer is not None:
                    event_writer.write(frame)
                if time.time() - record_start_time > 10.0:
                    event_writer.release()
                    event_writer = None
                    payload = {"elderID": current_elder_id, "eventType": " + ".join(current_alert_types), "videoPath": event_video_path}
                    try: 
                        r = requests.post(f"{WEBHOOK_BASE_URL}api_log_event.php", json=payload, timeout=2)
                        resp_data = r.json()
                        if resp_data.get("status") == "success":
                            global current_event_id
                            current_event_id = resp_data.get("eventID")
                    except Exception as e: print("Webhook error:", e)
                    machine_state = "LOCKED"

            elif machine_state == "GRACE":
                system_state["locked"] = True
                system_state["message"] = "GRACE PERIOD - SHOW THUMBS UP"
                time_left = max(0, 7.0 - (time.time() - grace_start_time))
                overlay = frame.copy()
                cv2.rectangle(overlay, (0, 0), (w, h), (0, 255, 255), -1)
                frame = cv2.addWeighted(overlay, 0.2, frame, 0.8, 0)
                cv2.putText(frame, f"ALERT DETECTED! CANCEL: {int(time_left)}s", (30, 80), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 255), 3)
                hand_results = hands.process(rgb)
                canceled = False
                if hand_results.multi_hand_landmarks:
                    for hand_lms in hand_results.multi_hand_landmarks:
                        if is_thumbs_up(hand_lms): canceled = True
                if canceled:
                    machine_state = "NORMAL"
                    system_state["locked"] = False
                    system_state["message"] = ""
                    current_alert_types = []
                    slump_start_time, tilt_start_time, pain_start_time = None, None, None
                elif time_left <= 0:
                    machine_state = "RECORDING"
                    record_start_time = time.time()
                    timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
                    local_video_path = f"../uploads/videos/event_{current_elder_id}_{timestamp}.webm"
                    event_video_path = f"uploads/videos/event_{current_elder_id}_{timestamp}.webm"
                    fourcc = cv2.VideoWriter_fourcc(*'VP80')
                    event_writer = cv2.VideoWriter(local_video_path, fourcc, 20.0, (w, h))

            else:
                face_results = face_mesh.process(rgb)
                pose_results = pose.process(rgb)
                has_pose = pose_results.pose_landmarks is not None
                has_face = face_results.multi_face_landmarks is not None
                is_person_present = has_pose or has_face
                face_alert, posture_alert, body_alert = False, False, False
                is_slumping, is_tilting, is_pain = False, False, False
                if not is_person_present:
                    slump_start_time, tilt_start_time, pain_start_time, hand_enter_time = None, None, None, None
                else:
                    hands_inside_box = False
                    is_lying_down = False
                    if has_pose:
                        plm = pose_results.pose_landmarks.landmark
                        l_shldr = get_coords(plm, 11, w, h)
                        r_shldr = get_coords(plm, 12, w, h)
                        l_hip = get_coords(plm, 23, w, h)
                        r_hip = get_coords(plm, 24, w, h)
                        nose_pose = get_coords(plm, 0, w, h)
                        l_wrist = get_coords(plm, 15, w, h)
                        r_wrist = get_coords(plm, 16, w, h)
                        left_horizontal = l_shldr is not None and l_hip is not None and abs(l_shldr[0] - l_hip[0]) > abs(l_shldr[1] - l_hip[1])
                        right_horizontal = r_shldr is not None and r_hip is not None and abs(r_shldr[0] - r_hip[0]) > abs(r_shldr[1] - r_hip[1])
                        shoulders_vertical = l_shldr is not None and r_shldr is not None and abs(l_shldr[1] - r_shldr[1]) > abs(l_shldr[0] - r_shldr[0])
                        if left_horizontal or right_horizontal or shoulders_vertical: is_lying_down = True
                        system_state["mode"] = "LYING DOWN" if is_lying_down else "SITTING/STANDING"
                        points_x = [pt[0] for pt in [l_shldr, r_shldr, l_hip, r_hip, nose_pose] if pt is not None]
                        points_y = [pt[1] for pt in [l_shldr, r_shldr, l_hip, r_hip, nose_pose] if pt is not None]
                        if points_x and points_y:
                            bx_min, bx_max = max(0, int(min(points_x) - 50)), min(w, int(max(points_x) + 50))
                            by_min, by_max = max(0, int(min(points_y) - 50)), min(h, int(max(points_y) + 50))
                            cv2.rectangle(frame, (bx_min, by_min), (bx_max, by_max), (255, 255, 0), 2)
                            tx_min, tx_max, ty_min, ty_max = bx_min, bx_max, by_min, by_max
                            if is_lying_down:
                                if (by_max - by_min) > (bx_max - bx_min):
                                    m = (bx_max - bx_min) * 0.25; tx_min += m; tx_max -= m
                                else:
                                    m = (by_max - by_min) * 0.25; ty_min += m; ty_max -= m
                            def in_box(pt): return pt is not None and tx_min < pt[0] < tx_max and ty_min < pt[1] < ty_max
                            if in_box(l_wrist) or in_box(r_wrist): hands_inside_box = True
                        if not is_lying_down and l_shldr is not None and r_shldr is not None and nose_pose is not None:
                            sw = np.linalg.norm(l_shldr - r_shldr)
                            my, mx = (l_shldr[1] + r_shldr[1]) / 2, int((l_shldr[0] + r_shldr[0]) / 2)
                            if (my - nose_pose[1]) < (sw * RELATIVE_SLUMP_THRESHOLD):
                                is_slumping = True
                                if slump_start_time is None: slump_start_time = time.time()
                                if draw_progress_bar(frame, mx - 50, int(my) - 60, slump_start_time, SLUMP_HOLD_DURATION, (0,0,255), "SLUMP"): posture_alert = True
                        mp.solutions.drawing_utils.draw_landmarks(frame, pose_results.pose_landmarks, mp_pose.POSE_CONNECTIONS)
                    if not is_slumping: slump_start_time = None
                    if hands_inside_box:
                        if hand_enter_time is None: hand_enter_time = time.time()
                        if draw_progress_bar(frame, 50, 100, hand_enter_time, HAND_HOLD_THRESHOLD, (0, 255, 255), "HANDS"): body_alert = True
                    else: hand_enter_time = None
                    if has_face:
                        lm = face_results.multi_face_landmarks[0].landmark
                        npt = p(lm, 1, w, h)
                        fw = dist(p(lm, 454, w, h), p(lm, 234, w, h))
                        if fw > 0:
                            if not is_lying_down:
                                t = calculate_tilt(p(lm, 33, w, h), p(lm, 263, w, h))
                                if t > TILT_THRESHOLD_DEG:
                                    is_tilting = True
                                    if tilt_start_time is None: tilt_start_time = time.time()
                                    if draw_progress_bar(frame, npt[0] - 50, npt[1] - 80, tilt_start_time, TILT_HOLD_DURATION, (255,0,255), "TILT"): posture_alert = True
                                else: tilt_start_time = None
                            else: tilt_start_time = None
                            ne = ((dist(p(lm, 159, w, h), p(lm, 145, w, h)) + dist(p(lm, 386, w, h), p(lm, 374, w, h))) / 2) / fw
                            nm = dist(p(lm, 13, w, h), p(lm, 14, w, h)) / fw
                            pv = 0
                            if ne < EYE_SQUINT_THRESHOLD: pv += 1
                            if nm > 0.10: pv += 1
                            if pv >= 2 or nm > 0.20 or ne < (EYE_SQUINT_THRESHOLD - 0.005):
                                is_pain = True
                                if pain_start_time is None: pain_start_time = time.time()
                                if draw_progress_bar(frame, npt[0] + int(fw/2) + 20, npt[1], pain_start_time, PAIN_HOLD_DURATION, (0,0,255), "PAIN"): face_alert = True
                            else: pain_start_time = None
                    if voice_alert: last_voice_time = time.time(); voice_alert = False
                    aa = (time.time() - last_voice_time) < 5.0
                    alerts = []
                    if posture_alert: alerts.append("POSTURE")
                    if body_alert: alerts.append("BODY")
                    if face_alert: alerts.append("FACE")
                    if aa: alerts.append("VOICE")
                    if alerts:
                        current_alert_types = alerts
                        machine_state = "GRACE"
                        grace_start_time = time.time()
                    else:
                        cv2.putText(frame, f"STATUS: NORMAL", (30, 50), cv2.FONT_HERSHEY_SIMPLEX, 0.8, (0, 255, 0), 2)

            ret, buffer = cv2.imencode('.jpg', frame)
            latest_frame = buffer.tobytes()
            time.sleep(0.01)

    except Exception as e: print(f"Thread error: {e}")
    finally:
        print("Releasing resources...")
        if session_writer: session_writer.release()
        if event_writer: event_writer.release()
        cap.release()
        is_monitoring = False
        print("Cleanup done.")

# ===================
# FLASK
# ===================
def generate_frames():
    global latest_frame, is_monitoring, exit_flag
    while not exit_flag and is_monitoring:
        if latest_frame:
            yield (b'--frame\r\n'
                   b'Content-Type: image/jpeg\r\n\r\n' + latest_frame + b'\r\n')
        time.sleep(0.05)

@app.route('/status')
def status_route():
    return jsonify({
        "active": is_monitoring,
        "locked": system_state["locked"],
        "message": system_state["message"],
        "mode": system_state["mode"],
        "machine_state": machine_state,
        "alert_types": current_alert_types,
        "current_event_id": current_event_id
    })

@app.route('/resolve_alert', methods=['POST'])
def resolve_alert():
    global machine_state, current_event_id, system_state, current_alert_types
    data = request.json
    action = data.get('action') # 'ack' or 'dismiss'
    event_id = data.get('eventID')

    if not event_id:
        return jsonify({"status": "error", "message": "No event ID provided"}), 400

    # 1. Update PHP database
    try:
        requests.get(f"{WEBHOOK_BASE_URL}api_resolve_event.php?action={action}&id={event_id}", timeout=2)
    except Exception as e:
        print("Error notifying PHP of resolution:", e)

    # 2. Reset Python State Machine
    machine_state = "NORMAL"
    system_state["locked"] = False
    system_state["message"] = ""
    current_alert_types = []
    current_event_id = None
    
    return jsonify({"status": "success"})

@app.route('/shutdown')
def shutdown_route():
    global exit_flag, current_elder_id, session_start_time, session_video_path_web
    exit_flag = True
    print("Shutdown signal...")
    if current_elder_id and session_start_time:
        payload = {
            "elderID": current_elder_id,
            "startTime": session_start_time.strftime("%Y-%m-%d %H:%M:%S"),
            "endTime": datetime.now().strftime("%Y-%m-%d %H:%M:%S"),
            "status": "Completed",
            "videoPath": session_video_path_web
        }
        try: requests.post(f"{WEBHOOK_BASE_URL}api_log_session.php", json=payload, timeout=2)
        except: pass
    
    # Give the frontend a moment to receive the final status if needed
    def force_exit():
        time.sleep(2.0)
        os._exit(0)
    threading.Thread(target=force_exit, daemon=True).start()
    return "Shutting down..."

@app.route('/set_elder', methods=['POST'])
def set_elder():
    global current_elder_id, session_start_time, session_video_path_web, session_video_path_local, session_writer
    global is_monitoring, monitor_thread, exit_flag
    data = request.json
    new_id = data.get('elderID')
    if is_monitoring and current_elder_id == new_id:
        return jsonify({"status": "already_running"})
    current_elder_id = new_id
    ts = datetime.now().strftime("%Y%m%d_%H%M%S")
    session_video_path_local = f"../uploads/videos/session_{current_elder_id}_{ts}.webm"
    session_video_path_web = f"uploads/videos/session_{current_elder_id}_{ts}.webm"
    session_start_time = datetime.now()
    session_writer = None 
    if not is_monitoring:
        exit_flag = False
        is_monitoring = True
        monitor_thread = threading.Thread(target=monitoring_loop, daemon=True)
        monitor_thread.start()
    return jsonify({"status": "success", "videoPath": session_video_path_web})

@app.route('/video_feed')
def video_feed():
    return Response(generate_frames(), mimetype='multipart/x-mixed-replace; boundary=frame')

if __name__ == '__main__':
    os.makedirs("../uploads/videos", exist_ok=True)
    app.run(host='0.0.0.0', port=5000, threaded=True)
