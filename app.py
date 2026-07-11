from flask import Flask, request, jsonify
import asyncio
from Crypto.Cipher import AES
from Crypto.Util.Padding import pad
from google.protobuf.json_format import MessageToJson
import binascii
import aiohttp
import requests
import json
import like_pb2
import like_count_pb2
import uid_generator_pb2
from datetime import datetime
import random

app = Flask(__name__)

# 💡 UBAH VERSI PATCH DI SINI KALAU GARENA UPDATE GAME
CURRENT_VERSION = "OB54" 

def load_tokens(server_name):
    try:
        # Sudah diubah dari "IND" ke "ID"
        if server_name == "ID":
            filename = "token_ind.json" 
        elif server_name in {"BR", "US", "SAC", "NA"}:
            filename = "token_br.json"
        else:
            filename = "token_bd.json"
            
        with open(filename, "r") as f:
            data = json.load(f)
            if isinstance(data, list) and len(data) > 0 and 'token' in data[0]:
                return data
            elif isinstance(data, list) and isinstance(data[0], str):
                return [{"token": token} for token in data]
            else:
                return []
    except FileNotFoundError:
        return []
    except Exception as e:
        print(f"Error loading tokens: {e}")
        return []

def encrypt_message(plaintext):
    key = b'Yg&tc%DEuh6%Zc^8'
    iv = b'6oyZDr22E3ychjM%'
    cipher = AES.new(key, AES.MODE_CBC, iv)
    padded_message = pad(plaintext, AES.block_size)
    encrypted_message = cipher.encrypt(padded_message)
    return binascii.hexlify(encrypted_message).decode('utf-8')

def create_protobuf_message(user_id, region):
    message = like_pb2.like()
    message.uid = int(user_id)
    message.region = region
    return message.SerializeToString()

async def send_request(encrypted_uid, token, url, semaphore):
    async with semaphore:
        edata = bytes.fromhex(encrypted_uid)
        headers = {
            'User-Agent': "Dalvik/2.1.0 (Linux; U; Android 9; ASUS_Z01QD Build/PI)",
            'Authorization': f"Bearer {token}",
            'Content-Type': "application/x-www-form-urlencoded",
            'X-GA': "v1 1",
            'ReleaseVersion': CURRENT_VERSION
        }
        try:
            async with aiohttp.ClientSession() as session:
                async with session.post(url, data=edata, headers=headers, timeout=5) as response:
                    return response.status
        except:
            return 500

async def send_multiple_requests(uid, server_name, url):
    region = server_name
    protobuf_message = create_protobuf_message(uid, region)
    encrypted_uid = encrypt_message(protobuf_message)
    
    tokens = load_tokens(server_name)
    if not tokens: 
        return []
    
    token_list = [t.get("token") for t in tokens if t.get("token")]
    
    if not token_list:
        return []
    
    random.shuffle(token_list)
    
    semaphore = asyncio.Semaphore(50)
    tasks = []
    for token in token_list:
        tasks.append(send_request(encrypted_uid, token, url, semaphore))
    
    if tasks:
        results = await asyncio.gather(*tasks, return_exceptions=True)
        return results
    return []

def create_protobuf(uid):
    message = uid_generator_pb2.uid_generator()
    message.krishna_ = int(uid)
    message.teamXdarks = 1
    return message.SerializeToString()

def enc(uid):
    protobuf_data = create_protobuf(uid)
    return encrypt_message(protobuf_data)

def decode_protobuf(binary):
    try:
        items = like_count_pb2.Info()
        items.ParseFromString(binary)
        return items
    except Exception as e:
        print(f"Error decoding: {e}")
        return None

def make_request(encrypt, server_name, token):
    # Perbaikan Indentasi & Mengubah IND menjadi ID
    if server_name == "ID":
        url = "https://client.id.freefiremobile.com/GetPlayerPersonalShow"
    elif server_name in {"BR", "US", "SAC", "NA"}:
        url = "https://client.us.freefiremobile.com/GetPlayerPersonalShow"
    else:
        url = "https://clientbp.ggpolarbear.com/GetPlayerPersonalShow"

    edata = bytes.fromhex(encrypt)
    headers = {
        'User-Agent': "Dalvik/2.1.0 (Linux; U; Android 9; ASUS_Z01QD Build/PI)",
        'Authorization': f"Bearer {token}",
        'Content-Type': "application/x-www-form-urlencoded",
        'X-GA': "v1 1",
        'ReleaseVersion': CURRENT_VERSION
    }

    try:
        response = requests.post(url, data=edata, headers=headers, verify=False, timeout=10)
        return decode_protobuf(response.content)
    except:
        return None

@app.route('/like', methods=['GET'])
def handle_requests():
    uid = request.args.get("uid")
    server_name = request.args.get("server_name", "").upper()
    key = request.args.get("key")

    if key != "Flash":
        return jsonify({"error": "Invalid or missing API key 🔑"}), 403

    if not uid or not server_name:
        return jsonify({"error": "UID and server_name are required"}), 400

    data_tokens = load_tokens(server_name)
    if not data_tokens:
        return jsonify({"error": "No tokens found for this server"}), 500
        
    token = data_tokens[0]['token']
    encrypt = enc(uid)

    before = make_request(encrypt, server_name, token)
    if before is None:
        return jsonify({"error": "Server error or Invalid Token/UID", "status": 0}), 200

    try:
        jsone_before = MessageToJson(before)
        data_before = json.loads(jsone_before)
        before_like = int(data_before.get('AccountInfo', {}).get('Likes', 0))
    except Exception as e:
        print(f"Before parse error: {e}")
        return jsonify({"error": "Data parsing failed", "status": 0}), 200

    # Menyambungkan routing endpoint LikeProfile ke server .id
    if server_name == "ID":
        url = "https://client.id.freefiremobile.com/LikeProfile"
    elif server_name in {"BR", "US", "SAC", "NA"}:
        url = "https://client.us.freefiremobile.com/LikeProfile"
    else:
        url = "https://clientbp.ggpolarbear.com/LikeProfile"

    try:
        loop = asyncio.new_event_loop()
        asyncio.set_event_loop(loop)
        loop.run_until_complete(send_multiple_requests(uid, server_name, url))
        loop.close()
    except Exception as e:
        print(f"Async error: {e}")

    after = make_request(encrypt, server_name, token)
    if after is None:
        return jsonify({"error": "Could not verify likes after command", "status": 0}), 200

    try:
        jsone_after = MessageToJson(after)
        data_after = json.loads(jsone_after)

        account_info = data_after.get('AccountInfo', {})
        
        after_like = int(account_info.get('Likes', 0))
        player_id = int(account_info.get('UID', 0))
        name = str(account_info.get('PlayerNickname', ''))
        level = int(account_info.get('Levels', 0))
        region = str(account_info.get('PlayerRegion', ''))
        
        if level == 0 and hasattr(after, 'AccountInfo') and hasattr(after.AccountInfo, 'Levels'):
            level = int(after.AccountInfo.Levels)

        like_given = after_like - before_like
        status = 1 if like_given != 0 else 2

        return jsonify({
            "LikesGivenByAPI": like_given,
            "LikesafterCommand": after_like,
            "LikesbeforeCommand": before_like,
            "PlayerNickname": name,
            "UID": player_id,
            "Level": level,
            "Region": region,
            "status": status,
            "Credits": "t.me/FL4SH_FF"            
        })
    except Exception as e:
        return jsonify({"error": str(e), "status": 0}), 500

if __name__ == '__main__':
    app.run()
