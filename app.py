from flask import Flask, request, jsonify
from flask_cors import CORS
from google import genai
import mysql.connector
import json
import random
import boto3
import math

app = Flask(__name__)
CORS(app)

def get_ssm_parameter(name, is_secure=True):
    try:
        ssm = boto3.client('ssm')
        response = ssm.get_parameter(Name=name, WithDecryption=is_secure)
        return response['Parameter']['Value']
    except Exception as e:
        print(f"🚨 AWS 키 호출 실패: {e}")
        exit()

# 이제 함수 하나로 두 가지를 모두 깔끔하게 가져올 수 있습니다!
api_key = get_ssm_parameter('wft-api-key')
client = genai.Client(api_key=api_key)

def get_db_connection():
    rds_host = get_ssm_parameter('db_host', is_secure=False)
    db_password = get_ssm_parameter('db_password')
    db_user = "root"
    return mysql.connector.connect(
        host=rds_host, 
        user=db_user,
        password=db_password,
        database="wft",
        port=2102
    )

@app.route('/api/recommend', methods=['POST'])
def recommend_api():
    data = request.json
    user_feedback = data.get('user_feedback', None)
    previous_food = data.get('previous_food', None)
    rejected_foods = data.get('rejected_foods', [])
    max_price = data.get('max_price', 20000)

    conn = get_db_connection()
    # 💡 [핵심 수정 1] 딕셔너리 형태로 데이터를 가져와 프론트엔드와 쉽게 맞물리게 합니다.
    cursor = conn.cursor(dictionary=True) 

    try:
        # --------------------------------------------------------
        # [분기 1] 최초 추천 (피드백이 없는 상태)
        # --------------------------------------------------------
        if user_feedback is None:
            print(f"\n🎲 [최초 추천] 예산 {max_price}원 이하 메뉴 중 랜덤 추천...")
            # 💡 [핵심 수정 2] 프론트엔드가 요구하는 모든 컬럼을 조회합니다.
            query = "SELECT store_id, food_name, price, food_image_url, recommend_reason FROM foods WHERE price <= %s"
            cursor.execute(query, (max_price,))
            rows = cursor.fetchall()
            
            if not rows: 
                return jsonify({"status": "no_more", "reason": "예산에 맞는 메뉴가 없습니다."})
            
            selected = random.choice(rows)
            print(f"💡 오늘의 추천 메뉴: {selected['food_name']} ({selected['price']}원)")
            
            # JSON 응답에 store_id, food_image_url 등을 모두 포함합니다.
            return jsonify({
                "status": "success", 
                "store_id": selected['store_id'],
                "food": selected['food_name'], 
                "price": selected['price'],
                "food_image_url": selected['food_image_url'],
                "recommend_reason": selected['recommend_reason']
            })

        # --- 아래부터는 명시적인 피드백(공백 포함)이 들어온 상황 ---
        if previous_food and previous_food not in rejected_foods:
            rejected_foods.append(previous_food)

        # --------------------------------------------------------
        # [분기 2] 공백 엔터 ("") 대응 -> 남은 것 중 무작위 랜덤
        # --------------------------------------------------------
        if user_feedback.strip() == "":
            print(f"\n🎲 [랜덤 대안] 특별한 이유 없이 넘기셨습니다. 남은 메뉴 중 고릅니다...")
            
            format_strings = ', '.join(['%s'] * len(rejected_foods)) if rejected_foods else "''"
            query = f"""
                SELECT store_id, food_name, price, food_image_url, recommend_reason 
                FROM foods 
                WHERE price <= %s AND food_name NOT IN ({format_strings})
            """
            
            params = [max_price] + rejected_foods if rejected_foods else [max_price]
            cursor.execute(query, tuple(params))
            rows = cursor.fetchall()
            
            if not rows: 
                return jsonify({"status": "no_more", "food": previous_food, "reason": "조건에 맞는 대안이 없습니다."})
            
            selected = random.choice(rows)
            print(f"💡 [랜덤 대안 추천]: {selected['food_name']} ({selected['price']}원)")
            
            return jsonify({
                "status": "success", 
                "store_id": selected['store_id'],
                "food": selected['food_name'], 
                "price": selected['price'],
                "food_image_url": selected['food_image_url'],
                "recommend_reason": selected['recommend_reason']
            })

        # --------------------------------------------------------
        # [분기 3] 정상 피드백 처리 및 키워드 추출
        # --------------------------------------------------------
        integrated_prompt = f"""Input: "{user_feedback}"

1. If the input is completely unrelated to food or dining, set "is_food": false.
2. "query": Convert negative expressions into positive traits in Korean.
3. "soup": Set to true if explicitly wants soup/broth, false if dry/fried, or null.
4. "spicy": Set to true if explicitly wants spicy, false if mild, or null.
5. Price Extraction: If the user mentions price constraints, extract them as pure integers. (e.g., "만원" -> 10000).
   - "new_min_price": The minimum price mentioned. Otherwise null.
   - "new_max_price": The maximum price mentioned. Otherwise null.
6. "category": If the user explicitly wants a specific cuisine (e.g., 한식, 중식, 일식, 양식), extract the exact category name. Otherwise null.

Output ONLY a valid JSON object in this exact format:
{{"is_food": true/false, "query": "Korean keyword", "soup": true/false/null, "spicy": true/false/null, "new_min_price": number/null, "new_max_price": number/null, "category": "string"/null}}"""
        
        
        ai_response = client.models.generate_content(model='gemini-3.1-flash-lite', contents=integrated_prompt)
        raw_text = ai_response.text.strip()
        
        # 마크다운 방어 로직
        if raw_text.startswith("```json"): 
            raw_text = raw_text.replace("```json", "").replace("```", "").strip()
        elif raw_text.startswith("```"): 
            raw_text = raw_text.replace("```", "").strip()
        
        result = json.loads(raw_text)
        
        if not result.get("is_food", True):
            return jsonify({
                "status": "blocked", 
                "food": previous_food, 
                "reason": "음식과 무관한 입력입니다. 다시 말씀해 주세요."
            })

        # --------------------------------------------------------
        # [분기 4] 구글 임베딩 변환 및 MariaDB 벡터 검색
        # --------------------------------------------------------
        new_search_query = result.get("query", "맛있는 점심")
        user_wants_soup = result.get("soup")
        user_wants_spicy = result.get("spicy")
        user_wants_category = result.get("category")
        

        min_price = data.get('min_price', 0)
        max_price = data.get('max_price', 20000)     

        new_min = result.get("new_min_price")
        if new_min is not None:
            min_price = new_min
            
        new_max = result.get("new_max_price")
        if new_max is not None:
            max_price = new_max
            print(f"💰 [예산 변동 감지] 새로운 최대 예산 적용: {max_price}원")

        # min_price = result.get("new_min_price") 
        # if min_price is None:
        #     min_price = 0  # 명시되지 않았다면 최소 금액은 0원 기본값
            
        # new_max = result.get("new_max_price")
        # if new_max is not None:
        #     max_price = new_max  # 프론트엔드의 max_price를 사용자의 실시간 요구사항으로 덮어씀
        #     print(f"💰 [예산 변동 감지] 새로운 최대 예산 적용: {max_price}원")

        # 사용자의 자연어 검색어를 벡터(임베딩)로 변환
        embed_response = client.models.embed_content(model='gemini-embedding-001', contents=new_search_query)
        search_vector = embed_response.embeddings[0].values
        
        truncated_search = search_vector[:768]
        magnitude = math.sqrt(sum(x**2 for x in truncated_search))
        normalized_search = [x / magnitude for x in truncated_search]
        vector_str = json.dumps(normalized_search)

        # 💡 [핵심 수정 5] 벡터 검색 쿼리에도 store_id와 추천 이유를 포함시킵니다.
        base_query = """
            SELECT store_id, food_name, price, food_image_url, recommend_reason
            FROM foods 
            WHERE price >= %s AND price <= %s
        """
        params = [min_price, max_price]

        # 1) 거절한 음식 필터링 추가 (이미 추천을 거절한 음식이 재등장하는 것 방지)
        if rejected_foods:
            format_strings = ', '.join(['%s'] * len(rejected_foods))
            base_query += f" AND food_name NOT IN ({format_strings})"
            params.extend(rejected_foods)

        # 2) 국물 유무 하드 필터링 추가 (True/False가 명시된 경우만 SQL에 이어 붙임)
        if user_wants_soup is not None:
            base_query += " AND soup = %s"
            params.append(1 if user_wants_soup else 0)

        # 3) 맵기 하드 필터링 추가 (True/False가 명시된 경우만 SQL에 이어 붙임)
        if user_wants_spicy is not None:
            base_query += " AND spicy = %s"
            params.append(1 if user_wants_spicy else 0)

        # 4) 💡 카테고리 하드 필터링 추가 (한식, 중식 등이 명시된 경우만 SQL에 이어 붙임)
        if user_wants_category is not None:
            base_query += " AND category = %s"
            params.append(user_wants_category)

        # 5) 최종 벡터 유사도 정렬 조건 추가 및 가장 유사한 1개 추출 제한
        base_query += " ORDER BY VEC_DISTANCE_COSINE(embedding, VEC_FromText(%s)) LIMIT 1;"
        params.append(vector_str)

        # 서버 콘솔 로깅으로 쿼리 조립 상태 확인
        print(f"🔍 [검색] 키워드: '{new_search_query}' | 예산: {min_price}~{max_price} | 국물: {user_wants_soup} | 맵기: {user_wants_spicy} | 카테고리: {user_wants_category}")
        
        # 조합된 하이브리드 쿼리 실행
        cursor.execute(base_query, tuple(params))
        best_match = cursor.fetchone()

        if best_match:
            return jsonify({
                "status": "success", 
                "store_id": best_match['store_id'],
                "food": best_match['food_name'], 
                "price": best_match['price'],
                "food_image_url": best_match['food_image_url'],
                "recommend_reason": best_match['recommend_reason'],
                "current_min_price": min_price,
                "current_max_price": max_price
            })
        else:
            return jsonify({"status": "no_more", "food": previous_food, "reason": "조건에 맞는 대안이 없습니다."})

    except Exception as e:
        print(f"🚨 서버 에러: {e}")
        return jsonify({"error": str(e)}), 500
    finally:
        # 에러가 나거나 정상 응답이 끝나도 자원은 항상 안전하게 닫기
        cursor.close()
        conn.close()

if __name__ == '__main__':
    app.run(host='0.0.0.0', port=5000, debug=True)