import csv
import math
import json
import os
import boto3
import mysql.connector
from google import genai
import google.genai.errors
import time

# 💡 리전 인식을 명확하게 하기 위해 ap-northeast-2 지정
def get_ssm_parameter(name, is_secure=True):
    try:
        ssm = boto3.client('ssm', region_name='ap-northeast-2')
        response = ssm.get_parameter(Name=name, WithDecryption=is_secure)
        return response['Parameter']['Value']
    except Exception as e:
        print(f"🚨 AWS 키 호출 실패 ({name}): {e}")
        exit()

# AWS Parameter Store에서 Gemini API 키 수급 및 클라이언트 초기화
api_key = get_ssm_parameter('wft-api-key')
client = genai.Client(api_key=api_key)

# 💡 데이터베이스 연결 함수 (SSL 인증 옵션 추가)
def get_db_connection():
    rds_host = get_ssm_parameter('db_host', is_secure=False)
    db_password = get_ssm_parameter('db_password')
    db_user = "root"  # admin으로 설정하셨다면 admin으로 변경
    
    return mysql.connector.connect(
        host=rds_host, 
        user=db_user,
        password=db_password,
        database="wft",
        port=2102,
        ssl_ca='/etc/pki/tls/certs/aws-rds-global-bundle.pem',
        ssl_verify_cert=True
    )

# 💡 [수정] 연결객체와 커서 객체를 올바르게 분리 선언
try:
    conn = get_db_connection()
    cursor = conn.cursor()
except Exception as e:
    print(f"🚨 데이터베이스 연결 실패: {e}")
    exit()

# 💡 CSV 파일 경로 자동 추적 탐색 규칙 (안전장치)
csv_file_path = '/var/www/html/real_foods.csv'
if not os.path.exists(csv_file_path):
    csv_file_path = '/home/ec2-user/whatfood/real_foods.csv'
if not os.path.exists(csv_file_path):
    csv_file_path = 'real_foods.csv'

with open(csv_file_path, newline='', encoding='utf-8') as csvfile:
    reader = csv.DictReader(csvfile) 
    
    for row in reader:
        store_id = row['store_id']
        food_name = row['food_name']
        food_image_url = row['food_image_url']
        price = row['price'] 
        recommend_reason = row.get('recommend_reason', '') 
        
        # 💡 이미 DB에 저장되어 있는 음식인지 확인 (이어받기 기능)
        check_query = "SELECT COUNT(*) FROM foods WHERE store_id = %s AND food_name = %s"
        cursor.execute(check_query, (store_id, food_name))
        (exists_count,) = cursor.fetchone()
        
        if exists_count > 0:
            print(f"⏭️ [{food_name}] 이미 DB에 존재하므로 건너뜁니다.")
            continue
            
        # --- 여기서부터 새로운 음식만 AI 작업 진행 ---
        prompt = f"""You are a food database engineer. 
Analyze the food: '{food_name}'.

Output ONLY a valid JSON object strictly matching this format:
{{
    "rich_description": "Write a rich, sensory description in 2-3 sentences including ingredients, taste, temperature, and texture in Korean.",
    "category": "Choose exactly one: 한식, 중식, 일식, 양식",
    "soup": true or false (true if it's a soup/stew/broth, false otherwise),
    "spicy": true or false (true if it is spicy/contains chili, false if it is mild/not spicy)
}}"""
        
        # 💡 [안전장치 오토루프] 429 에러가 발생하면 여기서 걸러내어 60초를 쉬게 만듭니다.
        while True:
            try:
                tag_response = client.models.generate_content(
                    model='gemini-3.1-flash-lite',
                    contents=prompt
                )
                raw_text = tag_response.text.strip()
                
                if raw_text.startswith("```json"): 
                    raw_text = raw_text.replace("```json", "").replace("```", "").strip()
                elif raw_text.startswith("```"): 
                    raw_text = raw_text.replace("```", "").strip()
                
                ai_data = json.loads(raw_text)
                
                # 💡 [수정] 태그 파싱 삭제
                rich_description = ai_data.get("rich_description", food_name)
                category = ai_data.get("category", "기타")
                soup = 1 if ai_data.get("soup", False) else 0 
                spicy = 1 if ai_data.get("spicy", False) else 0 
                
                # rich_description으로 임베딩 생성
                embed_response = client.models.embed_content(
                    model='gemini-embedding-001', 
                    contents=rich_description
                )
                vector = embed_response.embeddings[0].values
                
                break
                
            except google.genai.errors.ClientError as e:
                if "429" in str(e) or "RESOURCE_EXHAUSTED" in str(e):
                    print(f"\n🛑 [안내] API 한도 초과(429). 60초 대기 중...")
                    time.sleep(60)
                    print("🔄 대기 종료! 재개합니다.\n")
                else:
                    raise e 
            except json.JSONDecodeError:
                print(f"⚠️ [{food_name}] JSON 파싱 에러 발생. 재시도 합니다...")
                time.sleep(1)

        print(f"🆕 [{food_name}] AI 처리 완료 (카테고리: {category}, 국물: {soup}, 맵기: {spicy})")
        
        truncated_vector = vector[:768]
        magnitude = math.sqrt(sum(x**2 for x in truncated_vector))
        normalized_vector = [x / magnitude for x in truncated_vector]
        vector_str = json.dumps(normalized_vector)
        
        # 💡 [수정] INSERT 쿼리에서 ai_generated_tags 완전 제거
        insert_query = """
            INSERT INTO foods 
            (store_id, food_name, food_image_url, price, recommend_reason, 
             category, soup, spicy, rich_description, embedding) 
            VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, VEC_FromText(%s))
        """
        cursor.execute(insert_query, (
            store_id, food_name, food_image_url, price, recommend_reason, 
            category, soup, spicy, rich_description, vector_str
        ))
        
        conn.commit()
        print(f"  ➔ DB 저장 완료!\n")
        time.sleep(0.5)