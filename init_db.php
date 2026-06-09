<?php
// 1. 공통 DB 연결 파일 불러오기 
// (이 파일을 불러오는 순간 db.php 안의 로직이 실행되어 PDO 객체가 생성되고 customers 테이블이 세팅됩니다.)
require_once 'db.php';

try {
    // 2. stores 및 foods 테이블 생성 (IF NOT EXISTS)
    $createTablesSql = <<<SQL
        CREATE TABLE IF NOT EXISTS stores (
            store_id INT AUTO_INCREMENT PRIMARY KEY,
            store_name VARCHAR(30) NOT NULL UNIQUE,
            store_menu_url VARCHAR(150) NOT NULL,
            location VARCHAR(1000) NOT NULL,
            ggultip TEXT NULL
        );

        CREATE TABLE IF NOT EXISTS foods (
            food_id INT AUTO_INCREMENT PRIMARY KEY,
            store_id INT NOT NULL,
            food_name VARCHAR(100) NOT NULL,
            food_image_url VARCHAR(150),
            price INT NOT NULL,
            recommend_reason TEXT,
            rich_description TEXT,  
            embedding VECTOR(768) NOT NULL,
            soup TINYINT(1) DEFAULT 0,
            category VARCHAR(15) NOT null,
            spicy TINYINT(1) DEFAULT 0,
            FOREIGN KEY (store_id) REFERENCES stores(store_id) ON DELETE CASCADE,
            VECTOR INDEX (embedding) DISTANCE=cosine
        );
SQL;
    
    $pdo->exec($createTablesSql);
    echo "✅ 테이블 세팅 완료 (stores, foods)<br>";

    // 3. 데이터 중복 삽입 방지 로직 (최초 1회 실행)
    // 외래키(FOREIGN KEY) 제약 조건 때문에 데이터가 정상적으로 들어갔는지 foods 테이블을 기준으로 확인합니다.
    $stmt = $pdo->query("SELECT COUNT(*) FROM stores");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        
        // [주의] stores 데이터 INSERT
        // 외래키 제약조건 때문에 foods보다 stores 데이터가 무조건 먼저 들어가야 합니다.
        $insertStoresSql = <<<SQL
        -- 💡 여기에 이전에 작성하셨던 stores(매장) 데이터 INSERT 구문을 넣어주세요!
        INSERT INTO stores VALUES
        (null, '뚝배기집', '001.DDukbae/DDukbae_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi6Ap,2AMaV5,%EB%9A%9D%EB%B0%B0%EA%B8%B0%EC%A7%91,11717175,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '웨이팅 5~10분 있을 수 있음, 혼자갈 시 모르는 사람이 내 앞에 앉을 수 있음.'),
        (null, '잭아저씨 족발 보쌈', '002.Jack/Jack_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhXmv,2AMaPl,%EC%9E%AD%EC%95%84%EC%A0%80%EC%94%A8%EC%A1%B1%EB%B0%9C%EB%B3%B4%EC%8C%88%20%EC%A2%85%EB%A1%9C%EB%B3%B8%EC%A0%90,1374598059,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '화장실이 4층에 있어요 가기전 화장실 들렀다가기 필수'),
        (NULL, '불백당', '003.bulbaek/bulbaek_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhX7y,2AMaPo,%EB%B6%88%EB%B0%B1%EB%8B%B9,440280970,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '곱배기나 계란후라이를 추가하지 않고 기본으로만 주문 시 음식의 양이 매우 적음, 현재까지 오삼불백은 가게 사정으로 인해 주문이 불가했음'),
        (null, '황소고집', '004.hwangso/hwangso_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0eR,2AM8jO,%ED%99%A9%EC%86%8C%EA%B3%A0%EC%A7%91,11677544,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '양이 적어서 혹시 한 접시 더 나오나요라고 물어보지 말 것, 그 양이 맞습니다.'),
        (null, '진중우육면관', '005.jinjung_wu6/jinjung_wu6_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0Bo,2AM8eE,%EC%A7%84%EC%A4%91%20%EC%9A%B0%EC%9C%A1%EB%A9%B4%EA%B4%80%20%EB%B3%B8%EC%A0%90,1124361834,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '4년속 미슐랭. 자리가 좀 협소, 밥을 말아먹을 수 있음, 한 가게 사이로 양옆에 있음 자리 차면 다른쪽도 가보세요'),
        (null, '알돈', '006.aldon/aldon_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhP8j,2AM7pZ,%EC%95%8C%EB%8F%88%20%EB%B3%B8%EC%A0%90,1119621698,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '주문이 들어오면 튀기기 때문에 음식 나오는데 시간이 좀 걸림.'),
        (null, '순대실록', '007.sunsillok/sunsillok_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi207,2AM8GD,%EC%88%9C%EB%8C%80%EC%8B%A4%EB%A1%9D%20%EC%A2%85%EA%B0%81%EC%A0%90,1993785936,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '가까움, 좌석이 편함, 기본 반찬 추가는 셀프바 이용가능'),
        (null, '손정보쌈', '008.sonjung/sonjung_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0no,2AM9dI,%EC%86%90%EC%A0%95%EB%B3%B4%EC%8C%88%20%EC%A2%85%EB%A1%9C%EC%A0%90,2035632969,PLACE_POI/-/walk?c=19.00,0,0,0,dh','2인이상 시키면 한 번에 나오는데 따로 달라하면 따로 줍니다. '),
        (null, '다담정식', '009.dadam/dadam_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhNYR,2AMay2,%EB%8B%A4%EB%8B%B4%EC%A0%95%EC%8B%9D,17909110,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '계란말이(수제임)와 고기반찬이 리필이 됨!!'),
        (null, '순천가', '010.suncheon/suncheon_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi8g4,2AMeV1,%EC%88%9C%EC%B2%9C%EA%B0%80,38009499,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '3인 이상시 찌개와 고기 2인분을 시키면 가성비가 좋아진다. 혼자가면 반찬갯수가 많이 줄어듭니다.(방문시 최소 2인이상으로 갈 것)'),
        (NULL, '일품양품해장국', '011.illpoom_j3/illpoom_j3_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi8s0,2AMenY,%EC%9D%BC%ED%92%88%EC%96%91%ED%8F%89%ED%95%B4%EC%9E%A5%EA%B5%AD%20%EC%A2%85%EB%A1%9C3%EA%B0%80%EC%A0%90,1308844012,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '이 브랜드 해장국 집 요새 많음, 점바점인데 여기 지점이 (종각점보다) 진짜 잘함!'),
        (null, '동해루', '012.donghaeru/donghaeru_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zimFL,2AMaL8,%EB%8F%99%ED%95%B4%EB%A3%A8,1018714946,PLACE_POI/-/walk?c=16.00,0,0,0,dh', '조금멀고 찾기 힘듬, 지도잘 봐야함. 다른 메뉴 맛있음. 탕수육 존맛.'),
        (null, '백소정', '013.baeksojung/baeksojung_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZLM,2AM9nM,%EB%B0%B1%EC%86%8C%EC%A0%95%20%EC%A2%85%EA%B0%81%EC%A0%90,1089773822,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '후식 떡이 맛있습니다. 진짜 가까워요, 매장 넓고 좌석도 좋아서 혼밥하기 괜찮음.'),
        (null, '합천돼지국밥', '014.hapcheon/hapcheon_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi55b,2AMhk5,%ED%95%A9%EC%B2%9C%EB%8F%BC%EC%A7%80%EA%B5%AD%EB%B0%A5,1636084766,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '좌석이 편하진 않고, 재료가 신선하고, 찐 국밥이라고 느껴지는게 있음.  돼지의 진한 냄새를 싫어하시면 추천드리진 않습니다.'),
        (null, '참치공방', '015.chamchigong/chamchigong_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhVIk,2AM9SO,%EC%B0%B8%EC%B9%98%EA%B3%B5%EB%B0%A9%20%EC%A0%95%EB%8B%A4%EC%9A%B4%EC%A0%90,1733573733,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '알탕은 먹지마세요. 기본 반찬 추가는 직원호출하여 요청가능합니다.'),
        (null, '거구장', '016.geo_gu/geo_gu_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi1sX,2AMh6a,%EA%B1%B0%EA%B5%AC%EC%9E%A5,17922690,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '길찾기 조금 어려움'),
        (null, '청진옥', '017.chungjinok/chungjinok_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhIQf,2AMgEL,%EC%B2%AD%EC%A7%84%EC%98%A5,13544101,PLACE_POI/-/walk?c=16.00,0,0,0,dh', '조금 멀지만, 날씨 좋은 날 가기 좋음, 1937년 개업(서울에서 6번째로 오래됨)'),
        (null, '버거리', '018.burgery/burgerry_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhWXf,2AMd9K,%EB%B2%84%EA%B1%B0%EB%A6%AC%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,1511964521,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '음료 무한리필 가능, 먹다보면 기름이 흥건하게 떨어지니 음식 받고 냅킨 많이 뽑아가세요'),
        (null, '후니도니', '019.huni_doni/huni_doni_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhJQJ,2AMdZc,%ED%9B%84%EB%8B%88%EB%8F%84%EB%8B%88,37287956,PLACE_POI/-/walk?c=16.00,0,0,0,dh', '조금 멀어요, 모밀주문시 면 무한리필, 치돈 주문시 밥도 주고 우동도 줘서 배부르게 먹을 수 있음. '),
        (null, '된장예술과 술', '020.thensul/thensul_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0X2,2AM9gs,%EB%90%9C%EC%9E%A5%EC%98%88%EC%88%A0%EA%B3%BC%EC%88%A0,11678680,PLACE_POI/-/walk?c=19.00,0,0,0,dh', 'msg에 길들여있거나 자극적인 음식을 좋아하는 사람은 맛없다고 느낄 수 있음.'),
        (null, '종로사거리포차', '021.jong4po/021.jong4po_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZOM,2AMaL0,%EC%A2%85%EB%A1%9C%EC%82%AC%EA%B1%B0%EB%A6%AC%ED%8F%AC%EC%B0%A8%20%EC%A2%85%EA%B0%81%EB%B3%B8%EC%A0%90,1818437557,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '솔데스크 학생이라고 하면 계좌이체,현금- 7000원 카드-8000원입니다.'),
        (null, '이삭손칼국수손만두수제돈까스', '022.isaac_don/isaac_don_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi2Hl,2AM4KL,%EC%9D%B4%EC%82%AD%EC%86%90%EC%B9%BC%EA%B5%AD%EC%88%98%EC%86%90%EB%A7%8C%EB%91%90%EC%88%98%EC%A0%9C%EB%8F%88%EA%B9%8C%EC%8A%A4,1307265187,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '장소 때문에 뭔가 호불호 갈릴 수 있음, 여자친구랑 가면 안되는 식당 느낌(뭔말알?), 지하로 내려가서 왼쪽에 있는데 이런데에 식당이 있나 느낌인데 그 장소가 맞을 겁니다.'),
        (null, '맥도날드', '023.Mcdonald/Mcdonald_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zibC0,2AMdB0,%EB%A7%A5%EB%8F%84%EB%82%A0%EB%93%9C%20%EC%A2%85%EB%A1%9C3%EA%B0%80%EC%A0%90,18673146,PLACE_POI/-/walk?c=14.62,0,0,0,dh', '맥날 맛이고 나이드신 분들이 많습니다. 2층가서 맛있게 드세요.'),
        (null, '버거킹', '024.burgerking/burgerking_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi5jR,2AMc3P,%EB%B2%84%EA%B1%B0%ED%82%B9%20%EC%A2%85%EB%A1%9C%EC%A0%90,11782345,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '키오스크에 사람이 많아 오래걸릴 것 같으나 음식 생각보다 금방나옴.'),
        (null, 'rolling pasta', '025.rollingpa/rollingpa_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi55I,2AMaYQ,%EB%A1%A4%EB%A7%81%ED%8C%8C%EC%8A%A4%ED%83%80%20%EC%A2%85%EB%A1%9C%EC%A0%90,1252605117,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '혼밥하기 괜찮음, 양적을 수 있음. skt 가끔 할인 올라옴 참고바람.'),
        (NULL, '또보겠지떡볶이집', '026.DDobogetji/DDobogetji_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi3ca,2AMbdJ,%EB%98%90%EB%B3%B4%EA%B2%A0%EC%A7%80%EB%96%A1%EB%B3%B6%EC%9D%B4%EC%A7%91%20%EB%AA%BD%EA%B8%80%EB%AA%BD%EA%B8%80%EC%B2%AD%EA%B3%84%EC%A0%90,1395094671,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '혼밥은 힘들어요, 3명이상 갈 때 가시는 걸 추천 2명도 가격이 조금 애매해요;;'),
        (null, '데일리픽스 을지로', '027.daily_fix/daily_fix_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhNKe,2AM4xn,%EB%8D%B0%EC%9D%BC%EB%A6%AC%ED%94%BD%EC%8A%A4%20%EC%9D%84%EC%A7%80%EB%A1%9C,2039450393,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '실제로 가는데는 8~9분걸림 진짜 맛있고요, 인테리어 분위기가 정말 좋음, 돈이나 시간이 없어서 미국 못가는 분들 한 번 가서 플랙스 하고 오시죠. 미국 본토 바이브가 있습니다.'),
        (null, '파작', '028.pazak/pazak_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhUUv,2AM9l8,%ED%8C%8C%EC%9E%91,1032272629,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '웨이팅 가끔 있고 자리가 안 날 수 있어요 그럴 땐 포장해서 청계천에 앉아서 먹기'),
        (null, '역전우동', '029.yeokjeonudong/yeokjeonudong_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zibKS,2AMcdf,%EC%97%AD%EC%A0%84%EC%9A%B0%EB%8F%990410%20%EC%A2%85%EB%A1%9C3%EA%B0%80%EC%97%AD%EC%A0%90,1926457844,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '조금 멀고, 백종원 아저씨 브랜드 할인 가끔씩 하니깐 그 때 꼭 저렴하게 드시길~'),
        (null, '서브웨이', '030.subway/subway_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZPV,2AMd9K,%EC%8D%A8%EB%B8%8C%EC%9B%A8%EC%9D%B4%20%EC%A2%85%EB%A1%9C%EC%A0%90,17926788,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '픽 하기 어려울 경우 위 메뉴를 따라해보세요'),
        (null, '죠스떡볶이', '031.jaws_ddeok/jaws_ddeok_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhW6I,2AMbK0,%EC%A3%A0%EC%8A%A4%EB%96%A1%EB%B3%B6%EC%9D%B4%20%EC%A2%85%EA%B0%81%EC%A0%90,20652736,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '솔직히 맛은 기대하지말자. 분식 한 번에 여러 종류 먹고 싶다. 그럴 땐 이 가격에 여기 밖에 없을텐데..'),
        (null, '라밥', '032.rabap/rabap_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhY3a,2AMdaN,%EB%9D%BC%EB%B0%A5%20%EC%A2%85%EA%B0%81%EC%A0%90,2040395996,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '매장 깔끔하고 좋습니다. 배부르게 먹고 싶을 때 메뉴로 추천 드려요'),
        (null, '김가네', '033.kimgane/kimgane_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhWfa,2AMdd1,%EA%B9%80%EA%B0%80%EB%84%A4%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,35288457,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '살짝 좁고, 들어가자마자 주문하고 우동육수랑 반찬 셀프로 챙겨서 육수 많이 드시고 오세요.'),
        (null, '인사동칼국수', '034.insadongkal/insadongkal_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhYue,2AMhBn,%EC%9D%B8%EC%82%AC%EB%8F%99%EC%B9%BC%EA%B5%AD%EC%88%98,32340709,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '조금 멀고 찾기 힘들 수 있음. 건물 위에 중앙교회라고 써있는 건물 지하입니다.'),
        (null, '맘스터치', '035.moms_touch/moms_touch_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhVHX,2AMbXl,%EB%A7%98%EC%8A%A4%ED%84%B0%EC%B9%98%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,570774588,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '가끔 주문 밀리면 음식 조금 늦게 나올 수 있음.'),
        (null, '공샤브', '036.gongshabu/gongshabu_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhJZc,2AMegw,%EA%B3%B5%EC%83%A4%EB%B8%8C,1551717961,PLACE_POI/-/walk?c=16.00,0,0,0,dh', '조금 멀지만 인테리어 느낌있고 혼밥하기 정말 좋은 식당.'),
        (null, '동경암', '037.dongkyungam/dongkyungam_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhHnD,2AMbBb,%EB%8F%99%EA%B2%BD%EC%95%94,17909179,PLACE_POI/-/walk?c=16.00,0,0,0,dh', '조금 멀어요 근데 어떻게 맛있는데 나베 우동, 국물있는 메뉴가 들어간 돈까스집은 여기임'),
        (NULL, '엄용백돼지국밥', '038.mr.um_porksoup/mr.um_porksoup_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZuK,2AMgYN,%EC%97%84%EC%9A%A9%EB%B0%B1%20%EB%8F%BC%EC%A7%80%EA%B5%AD%EB%B0%A5,1473360805,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '야외에 평상이 있어서 타이밍이 좋으면 야외에서 식사 가능, 웨이팅이 있을 수 있음, 대기명단 등록 후 5분 정도 대기 후 입장함, 손님이 많아 시그니처인 엄용백 돼지국밥이 품절될 수 있음.'),
        (null, '종로분식', '039.jongno_bunsik/jongno_bunsik_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhVd4,2AMepL,%EC%A2%85%EB%A1%9C%EB%B6%84%EC%8B%9D,19409684,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '순두부는 별로입니다. 메뉴선택 잘해서 드세요'),
        (NULL, '종로곱육개장', '040.jongnogob6_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi8LU,2AMaTY,%EC%A2%85%EB%A1%9C%EA%B3%B1%EC%9C%A1%EA%B0%9C%EC%9E%A5,35992656,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '신라면을 매워하는 사람에게는 좀 맵게 느껴질 수 있음, 성시경도 왔다간집'),
        (null, '청진식당', '041.cheongjin_res/cheongjin_res_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi5nn,2AM8IS,%EC%84%9C%EC%9A%B8%20%EC%A2%85%EB%A1%9C%EA%B5%AC%20%EA%B4%80%EC%B2%A0%EB%8F%99%2033-3,09110135,ADDRESS_POI/-/walk?c=18.00,0,0,0,dh', '현재 리모델링 중이라하는데 공사끝나면 꼭 가세요 ㅠ, 2인 이상 가야 섞어 먹을 수 있음'),
        (null, '이리오너라', '042.2rionera/2rionera_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi01i,2AM9Oa,%EC%9D%B4%EB%A6%AC%EC%98%A4%EB%84%88%EB%9D%BC,20833080,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '현금 8000원 카드 9000원 입니다. 10장사면 5000원 할인해줌'),
        (null, '프리미엄 직원식당 을지로점', '043.jikdang/jikdang_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhQoB,2AM42d,%ED%94%84%EB%A6%AC%EB%AF%B8%EC%97%84%20%EC%A7%81%EC%9B%90%EC%8B%9D%EB%8B%B9%20%EC%9D%84%EC%A7%80%EB%A1%9C%EC%A0%90,1574412955,PLACE_POI/-/walk?c=17.00,0,0,0,dh', '스타벅스 조금 지나서 우측 코너로 돌면 지하로 들어가는 입구가 있음, 키오스크는 입장 후 뷔페 코너를 정면으로 좌측에 위치해있음'),
        (null, '수정식당', '044.sujung_res/sujung_res_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhQoB,2AM42d,%ED%94%84%EB%A6%AC%EB%AF%B8%EC%97%84%20%EC%A7%81%EC%9B%90%EC%8B%9D%EB%8B%B9%20%EC%9D%84%EC%A7%80%EB%A1%9C%EC%A0%90,1574412955,PLACE_POI/-/walk?c=14.00,0,0,0,dh', '쌈채소 다양하게 줌 가성비 괜찮게 느껴짐.'),
        (null, '아오리의 행방불명', '045.aori_where/aori_where_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi3jT,2AMdcv,%EC%95%84%EC%98%A4%EB%A6%AC%EC%9D%98%ED%96%89%EB%B0%A9%EB%B6%88%EB%AA%85%20%EA%B4%91%ED%99%94%EB%AC%B8%EC%84%BC%ED%8A%B8%EB%9F%B4%EC%A0%90,1213642320,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '사람이 별로 없고 좌석도 편함 혼밥하기 좋은 환경 편히 가도됨.'),
        (null, '구이와 찌개', '046.92and_stew/92and_stew_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi6GC,2AM9Gk,%EA%B5%AC%EC%9D%B4%EC%99%80%EC%B0%8C%EA%B0%9C,33252220,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '혼밥은 안됨, 두명이상 가세요 여기가 종각 음식점 중에 구찌라고 볼 수 있음.'),
        (null, '무쇠옥 종각본점', '047.musaeok/musaeok_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhVHK,2AMbet,%EB%AC%B4%EC%87%A0%EC%98%A5%20%EC%A2%85%EA%B0%81%EB%B3%B8%EC%A0%90,1262951670,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '음식 값이 계속 오릅니다. 가격 더 오르기 전에 가보시는 것을 추천합니다.'),
        (NULL, '선비꼬마김밥', '048.sunbi_kimbab/sunbi_kimbab_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhYLg,2AMc3A,%EC%84%A0%EB%B9%84%EA%BC%AC%EB%A7%88%EA%B9%80%EB%B0%A5%20%EC%A2%85%EA%B0%81%EC%A7%81%EC%98%81%EC%A0%90,1428983596,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '3명이가서 세트메뉴 먹으면 가성비 짱짱!'),
        (null, '보승회관', '049.boseung_res/boseung_res_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhXLD,2AMc5h,%EB%B3%B4%EC%8A%B9%ED%9A%8C%EA%B4%80%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,1389437504,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '매운 거 좋아하시는 분은 여기와야함 아주 맵게도 가능한 메뉴들이 있어요'),
        (null, 'ONDO', '050.ONDO/ONDO_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0DE,2AM8Xh,%EC%98%A8%EB%8F%84,1446714516,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '가까운 위치에 있고 조리시간이 좀 있어요.10분정도?'),
        (null, '샤오바오우육면', '051.shaobaou6/shaobaou6_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi1CS,2AM9Rf,%EC%83%A4%EC%98%A4%EB%B0%94%EC%98%A4%EC%9A%B0%EC%9C%A1%EB%A9%B4%20%EC%A2%85%EB%A1%9C%EB%B3%B8%EC%A0%90,1274897924,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '여기 본점임, 진중에 가려진 찐 맛집 진중 가봤으면 여기도 꼭 가보세요'),
        (null, '갓덴스시', '052.godthensushi/godthen_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZ47,2AMaNQ,%EA%B0%93%EB%8D%B4%EC%8A%A4%EC%8B%9C%20%EC%A2%85%EB%A1%9C%EC%A0%90,20315029,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '엄청 가까움, 웨이팅이 있을 수 있으나, 금방 자리가 생김'),
        (null, '우정함박', '053.wujung_hambak/wujung_hambak_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhVGU,2AM5yv,%EC%9A%B0%EC%A0%95%ED%95%A8%EB%B0%95,38009186,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '테이블 간격이 좁아서 이동 시 약간의 불편함이 있다.'),
        (null, '마라공방', '054.maragongbang/maragongbang_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi2BS,2AMbXh,%EB%A7%88%EB%9D%BC%EA%B3%B5%EB%B0%A9%20%EC%A2%85%EB%A1%9C3%EA%B0%80%EC%97%AD%EC%A0%90,1157899221,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '2000원당 100g이고 최소 8천원부터 주문 가능'),
        (null, '광희칼국수', '055.gwangheenoodle/gwangheenoodle_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi1Yr,2AM8Vt,%EA%B4%91%ED%9D%AC%EC%B9%BC%EA%B5%AD%EC%88%98,1118618775,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '평일 11시 ~ 15시까지만 영업'),
        (null, '미정국수0410', '056.mijung/mijung_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhW7a,2AMbDS,%EB%AF%B8%EC%A0%95%EA%B5%AD%EC%88%980410%20%EC%A2%85%EA%B0%81%EC%A0%90,30999818,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '카드 결제는 직원에게 따로 요청, 국수를 미리 삶아둬서 빨리 나오는거라 쫄깃한 식감은 없음'),
        (null, '에도마에텐동', '057.edomae/edomae_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZCG,2AM9hM,%EC%97%90%EB%8F%84%EB%A7%88%EC%97%90%ED%85%90%EB%8F%99%ED%95%98%EB%A7%88%EB%8B%A4%20%EC%A2%85%EB%A1%9C%EC%A0%90,1922543796,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '하마다 텐동 맛있게 먹는 방법(1) 튀김을 앞접시에 옮긴다. (2) 기호에 맞게 밥에 간장소스와 시치미를 뿌리고 온천계란과 함께 비빈다. (3) 옮겨 놓은 튀김과 함께 맛있게 먹는다.'),
        (null, '진대감', '058.jindaegam/jindaegam_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0r3,2AM9OQ,%EC%A7%84%EB%8C%80%EA%B0%90%20%EC%A2%85%EB%A1%9C%EC%A0%90,1246434193,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '다른 메뉴들도 많아요. 고기 좋아하시는 분 파김치 좋아하면 꿀메뉴입니다.'),
        (NULL, '홍반점', '059.hongbanjeom/hongbanjeom_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi1fX,2AM9eQ,%ED%99%8D%EB%B0%98%EC%A0%90%20%EC%A2%85%EA%B0%81%20%EB%B3%B8%EC%A0%90,1831135090,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '나갈때 먹으라고 아이스크림을 주심'),
        (null, '늘솜김밥', '060.neulsom/neulsom_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhWSH,2AMad0,%EB%8A%98%EC%86%9C%EA%B9%80%EB%B0%A5,145766958,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '07시 30분에 오픈해서 아침으로도 괜찮음, 13시 30분까지만 영업.'),
        (NULL, '육결', '061.yukgyul/yukgyul_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi0Gx,2AM9dA,%EC%9C%A1%EA%B2%B0%20%EC%A2%85%EB%A1%9C%EB%B3%B8%EC%A0%90,1553128783,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '맑은 국물의 국밥을 반정도 먹고 얼큰 국물에 들어가는 피쉬소스, 다대기를 넣어서 먹는 것을 추천'),
        (null, '정원순두부', '062.gardensuntofu/gardensuntofu_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi1Zv,2AM8oF,%EC%A0%95%EC%9B%90%EC%88%9C%EB%91%90%EB%B6%80,1063235840,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '후식으로 요구르트가 제공되고 있으니 꼭 챙겨드세요.'),
        (null, '신마포갈매기', '063.shinmapogalmae/shinmapogalmae_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhVJs,2AMam2,%EC%8B%A0%EB%A7%88%ED%8F%AC%EA%B0%88%EB%A7%A4%EA%B8%B0%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,1499069995,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '직원분 추천메뉴입니다. 저는 개인적으로 맛은 평범하다고 생각.'),
        (null, '육전국밥', '064.6jeongukbab/6jeongukbab_M.jpg','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhXak,2AMc4X,%EC%9C%A1%EC%A0%84%EA%B5%AD%EB%B0%A5%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,1625063962,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '여기 전이 진짜 맛있는데 나중에 저녁으로 전까지 (feat.소주) 드셔보셔요'),
        (null, '싸다김밥', '065.ssadakimbab/ssadakimbab_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi3JT,2AMaHO,%EC%8B%B8%EB%8B%A4%EA%B9%80%EB%B0%A5%20%EC%A2%85%EB%A1%9C%EA%B4%80%EC%B2%A0%EC%A0%90,1531389540,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '매장이 다소 협소하다. 싸다가 김밥을 싸다 느낌이긴해요'),
        (null, '한사리감자탕', '066.hansarigamjatang/hansari_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zi28c,2AMa7l,%ED%95%9C%EC%82%AC%EB%A6%AC%EA%B0%90%EC%9E%90%ED%83%95%26%EB%BC%88%EA%B5%AC%EC%9D%B4%20%EC%A2%85%EB%A1%9C%EC%A0%90,2042952699,PLACE_POI/-/walk?c=19.00,0,0,0,dh', '나이들면 그냥 막 다 귀찮잖아요 편하게 먹기에는 추천드립니다. 밥 무한리필'),
        (null, '만보성', '067.10000bosung/10000bosung_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhYJq,2AM9BN,%EB%A7%8C%EB%B3%B4%EC%84%B1,17920251,PLACE_POI/-/walk?c=20.00,0,0,0,dh', '김치볶음밥 먹으면 짬뽕국물도 줌.'),
        (null, '프레퍼스', '068.preppers/preppers_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhZ2i,2AMc14,%ED%94%84%EB%A0%88%ED%8D%BC%EC%8A%A4%20%EB%8B%A4%EC%9D%B4%EC%96%B4%ED%8A%B8%20%ED%91%B8%EB%93%9C%20%EC%A2%85%EA%B0%81%EC%97%AD%EC%A0%90,2075428817,PLACE_POI/-/walk?c=15.73,0,0,0,dh', '2시부터 5시사이에 가면 1000원 할인됩니다.'),
	    (NULL, '미진', '069.mijin/mijin_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhK5k,2AMdwp,%EA%B4%91%ED%99%94%EB%AC%B8%EB%AF%B8%EC%A7%84,11680222,PLACE_POI/-/walk?c=14.00,0,0,0,dh', '조금 멀고 웨이팅 있을 수 있어요 하지만 자리 금방남 미슐랭 9년연속임. 끝'),
	    (null, '종로반상회', '070.jongnobasang/jongnobansang_M.png','https://map.naver.com/p/directions/3zhZMD,2AMayK,%EC%A2%85%EB%A1%9C%EC%BD%94%EC%95%84%EB%B9%8C%EB%94%A9,18669094,PLACE_POI/3zhTql,2AMaLQ,%EC%A2%85%EB%A1%9C%EB%B0%98%EC%83%81%ED%9A%8C,1444069003,PLACE_POI/-/walk?c=18.00,0,0,0,dh', '매장이 조금 어두워요, 밥 무한리필이고, 점심에 막걸리도 무제한?(이건 비밀)');

SQL;
        // stores 데이터 구문을 완성하신 후 아래 주석(//)을 해제하세요.
        $pdo->exec($insertStoresSql); 

        // foods 데이터 INSERT (방금 전달해주신 전체 데이터)
//         $insertFoodsSql = <<<SQL
//         INSERT INTO foods (food_id, store_id, food_name, food_image_url, price, recommend_reason) VALUES
//         (NULL, 1, '된장찌개', '001.DDukbae/DDukbae_F.png', 7000, '비빔밥과의 조합이 좋다, 이 근래 가성비 갑'),
//         (NULL, 1, '우렁된장', '001.DDukbae/DDukbae_urung_F.png', 8000, '된장 맛있는데 재밌는 식감을 원하시면 우렁 된장 좋습니다.'),
//         (NULL, 2, '보쌈정식', '002.Jack/Jackbosam_F.png', 9000, '보쌈을 점심에 먹기 가능한 곳, 반찬 정갈하게 잘 나옴.'),
//         (NULL, 2, '제육정식', '002.Jack/Jackjeyuk_F.png', 8000, '제육에 불향나고, 양도 은근히 많음, 반찬 정갈하게 잘 나옴.'),
//         (NULL, 3, '고추장불백 + 후라이추가', '003.bulbaek/bulbaek_F.png', 6000, '가성비, 이 집 메뉴중 이게 제일 괜찮아요'),
//         (NULL, 4, '고추불고기 백반', '004.hwangso/hwangso_F.jpg', 8000, '고기 불향 제대로, 고기 맛있어요.'),
//         (NULL, 5, '우육면', '005.jinjung_wu6/jinjung_wu6_F.jpg', 12000, '그냥 정말 맛있어요, 든든합니다.'),
//         (NULL, 6, '등심카츠', '006.aldon/aldon_Deungsim_F.png', 14000, '맛있어요. 종각 일식 등심돈까스 여기 1위'),
//         (NULL, 6, '안심카츠', '006.aldon/aldon_ansim_F.png', 15000, '맛있어요. 종각 일식 안심돈까스 여기 1위'),
//         (NULL, 6, '치즈카츠', '006.aldon/aldon_cheese_F.png', 15000, '맛있어요. 종각 일식 치즈돈까스 아마? 여기 1위'),
//         (NULL, 7, '순대국', '007.sunsillok/sunsillok_sundae_F.png', 11000, '국물이 깔끔하고 순대가 맛있음 타 순대국 프랜차이즈랑 비교시 여기 순대국 탑티어임'),
//         (NULL, 7, '떡만두국', '007.sunsillok/sunsillok_mandu_F.png', 10000, '만두러버들은 여기서 떡만두국 드세요.'),
//         (NULL, 8, '얼큰 알곤이 칼국수', '008.sonjung/sonjung_algon_F.png', 11000, '알 좋아하고 매운 거 좋아하는 사람 딱입니다.'),
//         (NULL, 8, '한우사골칼국수', '008.sonjung/sonjung_sagol_F.png', 10000, '깔끔한 고기 칼국수 들어가시는 분 여기 괜찮습니다.'),
//         (NULL, 9, '다담정식', '009.dadam/dadam_F.png', 10000, '찌개나 국이 나오고, 고기, 생선, 계란말이가 반찬으로 나옴. 맛도 있음'),
//         (NULL, 10, '간장제육', '010.suncheon/suncheon_ganjang_F.png', 10000, '간 적절하고 양도 준수합니다. 일단 이 가게는 반찬이 10가지는 나옴(상추 많이 줌)'),
//         (NULL, 10, '김치돼지찌개', '010.suncheon/suncheon_kimchi_F.png', 10000, '김치찌개 돼지 통으로 넣고 가위로 잘라서 넣는 느낌 아시죠? + 일단 이 가게는 반찬이 10가지는 나옴(상추 많이 줌)'),
//         (NULL, 11, '양평해장국', '011.illpoom_j3/illpoom_j3_F.png', 10000, '해장이 필요, 선지 좋아하신다면 여기 선지 상태 및 간이 진짜 좋음.'),
//         (NULL, 12, '간짜장 곱빼기','012.donghaeru/donghaeru_F(1).jpg', 9000, '원래 생각하는 간짜장보다 덜 자극적임, 근데 먹다보면 맛있음. 왜 슴슴하게 만들었는지 설득이된다? 소스랑 면이 잘 비벼집니다.'),
//         (NULL, 13, '마제소바', '013.baeksojung/baeksojung_maje__F.png', 10900, '백소정은 마제소바 맛있기로 유명함'),
//         (NULL, 13, '모짜렐라 치즈카츠', '013.baeksojung/baeksojung_cheese_F.png', 13500, '여기 치즈카츠 만족스러움 가까운 돈까스집은 여기'),
//         (NULL, 14, '돼지국밥', '014.hapcheon/hapcheon_pork_F.png', 8000, '깍두기랑, 김치가 맛있고 순대 간이 기본찬으로 나옴'),
//         (NULL, 14, '순대국밥', '014.hapcheon/hapcheon_sundae_F.png', 8000, '깍두기랑, 김치 맛있음, 여기 재료가 신선함'),
//         (NULL, 15, '회덮밥', '015.chamchigong/chamchigong_hoidub_F.png', 12000, '회덮밥을 넓고 쾌적한 공간에서 먹고 싶으면 여기 양이 괜찮아요.'),
//         (NULL, 15, '알밥', '015.chamchigong/chamchigong_albab_F.png', 12000, '알밥을 넓고 쾌적한 공간에서 먹고 싶으면 여기 양이 괜찮아요.'),
//         (NULL, 16, '볶음밥', '016.geo_gu/geo_gu_bab_F.png', 9000, '볶음밥에 후라이를 올려주고 맛있음, 깍두기 반찬 있어서 조합이 좋아요.'),
//         (NULL, 16, '짜장면', '016.geo_gu/geo_gu_jjajang.png', 8000, '여기 짜장소스 괜찮습니다. 고추가루 뿌리고 깍두기 얹어 먹으면 꿀맛입니다.'),
//         (NULL, 17, '양선지해장국', '017.chungjinok/chungjinok_F.png', 12000, '든든하게 먹을 수 있습니다. 깍두기 굿,맵찔이도 먹을 수 있는 맵지 않음 해장국.'),
//         (NULL, 18, '버거리버거 세트', '018.burgery/burgery_F.png', 10900, '종각에서 무난 수제버거 픽으로 추천'),
//         (NULL, 18, '더블클래식치즈버거세트바', '018.burgery/burgery_doubleclassic_F.png', 12600, '버거리 맛도리 메뉴 수제,치즈 버거는 이거 추천'),
//         (NULL, 19, '치즈 돈까스', '019.huni_doni/huni_doni_F.png', 15000, '치즈에 체다를 섞었다. 정말 고소하고 후니도니 최고의 메뉴'),
//         (NULL, 19, '냉모밀 미니돈까스', '019.huni_doni/huni_doni_momil_F.png', 12000, '구성이 좋고 여름에 드시러 가는 걸 추천하고 면 무한리필~'),
//         (NULL, 20, '된장정식', '020.thensul/thensul_F.png', 12000, '건강식, 슴슴함과 다이어트에 도움될 만한 음식'),
//         (NULL, 21, '한식뷔페', '021.jong4po/021.jong4po_F.jpg', 7000, '학원 지하1층 솔데스크 학생이라고 하면 계좌이체, 현금-7000원 카드-8000원입니다.'),
//         (NULL, 22, '돼지불백', '022.isaac_don/isaac_don_F.jpg',5900, '가격이 말이 안됩니다.'),
//         (NULL, 22, '김치두루치기덮밥','022.isaac_don/issac_don_kimdudub_F.png',5900, '가격이 말이 안됩니다.'),
//         (NULL, 23, '상하이 스파이시 치킨 버거 세트', '023.Mcdonald/Mcdonald_F.png', 6000, '맥날 런치세트 저렴한데 이 가격인지 잘 모르시는 것 같아서 ㅎㅎ'),
//         (NULL, 24, '어플 쿠폰에 있는 메뉴(와퍼세트기준)', '024.burgerking/burgerking.png', 7900, '맛있긴 함, 다먹으면 배부름.'),
//         (NULL, 25, '버터갈릭파스타', '025.rollingpa/rollingpa_F.png', 6900, '가성비 일단 좋음, 빵도 나와서 구성 괜찮음.'),
//         (NULL, 26, '또떡 + 날치알볶음밥', '026.DDobogetji/DDobogetji_F.png', 9000, '즉석떡볶이 좋아하시는 분들 여기서 드시면 좋아요 갠적으로 신당동보다 맛있고 날치알볶음밥 강추드림.'),
//         (NULL, 27, '베이컨치즈버거', '027.daily_fix/daily_fix_F.png', 12900, '수제버거 맛으로는 1티어 재료들이 정말 신선해요'),
//         (NULL, 27, '트러플 머슈룸버거', '027.daily_fix/daily_fix_truffle_F.png', 13900, '여기 트러플 머슈룸 버거 먹으면 버거킹 가는 거 고민됨(맛있어서)'),
//         (NULL, 28, '누블라 잠봉 트러플 허니버터', '028.pazak/pazak_jambong_F.png', 10800, '달달한 거 좋아하시는 분들은 이것 드시고 여긴 빵이 진짜 맛있어요'),
//         (NULL, 28, 'RTM(알티엠)', '028.pazak/pazak_RTM_F.png', 11800, '기본 충실한 맛 서브웨이 질리시면 여기와서 드시죠 빵이 진짜 맛있습니다. ~~'),
//         (NULL, 29, '간장양념구이덮밥', '029.yeokjeonudong/yeokjeonudong_F.png', 8000, '역전우동 꿀 메뉴 간장베이스 제육 좋아하면 딱임 취향 맞으면 계속먹게 됨.'),
//         (NULL, 30, '치킨베이컨아보카도 ', '030.subway/subway_F.jpg', 8500, '개꿀조합 추천 (빵은 위트, 슈레드치즈, 야채 다넣고 올리브 할라피뇨 많이, 바비큐 홀스래디쉬 소스)'),
//         (NULL, 31, '점심죠스떡볶이 1인세트 ', '031.jaws_ddeok/jaws_ddeok_F.png', 8500, '혼자 분식 땡길 때 여기 밖에 없음'),
//         (NULL, 32, '참치김밥 + 계란 2개라면', '032.rabap/rabap_F.png', 10000, '배고플 때 라면에 김밥 배부르게 먹을 때 강추 여기 참치김밥 맛있어요'),
//         (NULL, 33, '철판치즈김치볶음밥', '033.kimgane/kimgane_F.png', 10000, '이게 이 집에서 젤 맛있음.'),
//         (NULL, 34, '칼국수', '034.insadongkal/insadongkal_Kal_F.jpg', 7500, '칼국수 국물 겁나 찐해요 김치가 정말 맛있습니다. 1티어'),
//         (NULL, 34, '돌솥비빔밥', '034.insadongkal/insadongkal_dolsot_F.png', 7500, '돌솥 비빔밥 깔끔하고 반찬이 진짜 맛있어요'),
//         (NULL, 34, '순두부찌개', '034.insadongkal/insadongkal_suntofu_F.png', 7500, '순두부 맛있어요 그리고 반찬이 진짜 맛있어요'),
//         (NULL, 35, '싸이버거세트', '035.moms_touch/moms_touch_psy_F.png', 7700, '싸이버거 드세요 돌고돌아 싸이버거가 짱입니다.'),
//         (NULL, 35, '싱글떡강정세트바', '035.moms_touch/moms_touch_singset_F.png', 11600, '배부르게 먹을 때는 맵고 달달한 싱글 떡강정 세트 드셔보세요'),
//         (NULL, 36, '공샤브 소고기 샤브샤브 세트', '036.gongshabu/gongshabu_F.png', 10900, '육수 4개중 하나 고를 수 있고 가성비 좋게 먹을 수 있음.'),
//         (NULL, 37, '김치우동정식,', '037.dongkyungam/dongkyungam_udong.png', 14000, '국물 육수를 잘 만드시는 듯 국물 맛이 깊고 좋습니다.'),
//         (NULL, 37, '김치카츠나베', '037.dongkyungam/dongkyungam_kimchi_M.png', 15000, '여기 시그니처 메뉴 찐맛 나베는 이게 일등 아닐까? 싶음'),
//         (NULL, 38, '부산식 돼지국밥', '038.mr.um_porksoup/mr.um_porksoup_busan.jpg', 13000, '전날 술을 먹지 않았어도 해장되는 느낌, 토렴식이라 매우 뜨끈하다.'),
//         (NULL, 39, '꽁치찌개', '039.jongno_bunsik/jongno_bunsik_F.jpg', 6000, '이 가격에 꽁치찌개를? 가성비가 좋음'),
//         (NULL, 39, '비빔밥', '039.jongno_bunsik/jongno_bunsik_bibim_F.jpg', 6000, '이 가격에 이런 비빔밥 못먹어요 반찬도 4개줍니다.'),
//         (NULL, 40, '곱창육개장', '040.jongnogob6/040.jongnogob6_F.jpg', 13000, '육개장의 칼칼함과 곱창의 은은한 고소함이 은근 잘어울림, 김을 매일 아침 구워서 맛이 고소함'),
//         (NULL, 41, '오징어 + 불고기', '041.cheongjin_res/cheongjin_res_F.png', 12000, '오징어랑 불고기 섞어먹는 꿀 조합 음식'),
//         (NULL, 42, '한식뷔페', '042.2rionera/2rionera_F.png', 8000, '집밥 느낌의 뷔페 메뉴보고 마음에 들면 들어가시죠~'),
//         (NULL, 43, '뷔페, 일품정식', '043.jikdang/jikdang_F.jpg', 8900, '간이 쎄지 않고 적절함, 매일 메뉴가 바뀌어서 질릴 확률이 적고 쾌적함. 뷔페 1티어'),
//         (NULL, 44, '쌈밥', '044.sujung_res/sujung_res_F.png', 11000, '구성이 알차고 제육 1티어'),
//         (NULL, 45, '돈코츠라멘', '045.aori_where/aori_where_F.png', 11500, '차슈맛있고 종각 주변 라멘집 중 추천함.'),
//         (NULL, 46, '2인구이 정식세트', '046.92and_stew/92and_stew_F.png', 10000, '바베큐제육, 고등어를 이가격에 이유가 필요한가?'),
//         (NULL, 47, '돼지김치구이', '047.musaeok/musaeok_F.png', 11900, '양이 많고 돼지고기 좋은 한돈 쓰심'),
//         (NULL, 48, '매콤진미 꼬마김밥', '048.sunbi_kimbab/sunbi_kimbab_F.jpg', 5200, '매운 것 좋아하는 분은 강추, 간단하게 장국이랑 즐겨요 배가 별로 안고프고 적게 드실 분은 여기로'),
//         (NULL, 49, '얼큰수육국밥', '049.boseung_res/boseung_res_F.png', 12000, '깔끔함. 얼큰한 것 좋아하는 분 추천.'),
//         (NULL, 50, '큐브스테이크 덮밥', '050.ONDO/ONDO_F2.png', 13800, '고기가 맛있어요 감태랑 먹으면 개꿀맛, 반찬이나 먹기 좋게 이쁘게 잘나옵니다.'),
//         (NULL, 50, '우리소 육회 덮밥', '050.ONDO/ONDO_F1.png', 12900, '육회 덮밥은 아마 먹을 만한 곳이 여기밖에 없을 겁니다. 반찬 정갈하게 잘나와요'),
//         (NULL, 51, '우육면 3번 굵기', '051.shaobaou6/shaobaou6_F.png', 11000, '면 굵기 조절가능, 국물이 존맛.'),
//         (NULL, 52, '실속10p세트', '052.godthensushi/godthen_F.png', 13900, '맛있고 재료가 신선하고 회전률이 빠르다'),
//         (NULL, 53, '수제로제함박스테이크', '053.wujung_hambak/wujung_hambak_F.png', 13900, '샐러드 많이 주고 맛있음, 함박은 촉촉하고 부드러움'),
//         (NULL, 54, '마라탕', '054.maragongbang/maragongbang_F.png', 12000, '토핑이 많고 땅콩소스 무한제공'),
//         (NULL, 55, '직화 소고기 칼국수', '055.gwangheenoodle/gwangheenoodle_F.jpg', 9500, '칼국수와 직화 고기를 같이 먹을 수 있고, 김치가 맛있다.'),
//         (NULL, 56, '멸치국수 + 명란마요 미니밥', '056.mijung/mijung_F.png', 8500, '저렴하고 맛도 무난하고 음식이 빨리 나옴, 맛보다는 혼밥, 빨리 후다닥 먹을 땐 이곳'),
//         (NULL, 57, '텐동', '057.edomae/edomae_F.png', 15000, '튀김은 겉바속촉, 기름기가 많지 않아서 먹다 느끼해지는 느낌은 덜 하다.'),
//         (NULL, 58, '한돈파김치전골밥상', '058.jindaegam/jindaegam_F.png', 12000, '파김치, 돼지고기 조합 좋습니다~ 김치찌개 좋아하는 분 드셔보세요'),
//         (NULL, 59, '짬뽕', '059.hongbanjeom/hongbanjeom_F.png', 11000, '맛있는 짬뽕, 불향이 기가막힘 불향 좋아하시면 추천'),
//         (NULL, 60, '숯불고기 김밥', '060.neulsom/neulsom_bulgogi.png', 4800, '가성비가 좋고 불향이 나서 풍미가 좋음 + 컵라면이랑 점심 뚝딱 추천'),
//         (NULL, 60, '숯불제육 김밥', '060.neulsom/neulsom_jeyuk.png', 4800, '불향 가득한 제육 김밥 컵라면이랑 점심 뚝딱 추천'),
//         (NULL, 61, '맑은돼지국밥', '061.yukgyul/yukgyul_F.png', 11000, '술마시고 다음날 해장하기 좋음'),
//         (NULL, 61, '항정돼지국밥', '061.yukgyul/yukgyul_hangjeong_F.png', 14000, '잡내 안나고 부위가 항정이라 그런지 고기가 짱 부드러움'),
//         (NULL, 62, '차돌 순두부', '062.gardensuntofu/gardensuntofu_F.png', 11500, '서브 메뉴로 떡볶이가 제공되고, 가격도 나름 저렴하다.'),
//         (NULL, 63, '일식 왕 돈가스', '063.shinmapogalmae/shinmapogalmae_F.png', 10000, '바삭한 돈까스 좋아하는 분은 이리로.'),
//         (NULL, 64, '육전 소고기 국밥', '064.6jeongukbab/6jeongukbab_F.png', 11000, '맛도 무난하고 매장도 넓어서 여러명이 가기 좋다.'),
//         (NULL, 65, '라면 + 김밥', '065.ssadakimbab/ssadakimbab_F.png', 9000, '다양한 분식 메뉴가 있어서 무난하게 가기 좋다.'),
//         (NULL, 66, '순살 뼈해장국', '066.hansarigamjatang/hansarigamjatang_F1.png', 12000, '뼈 안발라도 됨'),
//         (NULL, 66, '맑은 뼈해장국', '066.hansarigamjatang/hansarigamjatang_F2.png', 11000, '그냥 생각보다 맛있음'),
//         (NULL, 67, '김치볶음밥', '067.10000bosung/10000bosung_F.png', 9500, '종각에서 김치볶음밥 먹고 싶다. 김밥지옥 같은 곳보다 김치볶음밥은 여기가 더 맛있습니다.'),
//         (NULL, 68, '치킨플레이트 샐러드', '068.preppers/preppers_F2.png', 7900, '식단하시는 분들 맛있게 단백질 채우기 좋아요'),
//         (NULL, 68, '비프샐러드 파스타', '068.preppers/preppers_F1.png', 13900, '닭은 질리고 플렉스하고 싶은 다이어터님 이거 개맛있습니다.'),
//         (NULL, 69, '냉메밀', '069.mijin/mijin_F.png', 12000, '냉메밀 우리나라 1티어'),
//         (NULL, 70, '점심 제육볶음 반상', '070.jongnobasang/jongnobansang_F(1).png', 10000, '맛 괜찮고 반찬 4개에 미역국 까지 나옴'),
//         (NULL, 70, '점심 김치찜 반상', '070.jongnobasang/jongnobansang_F(2).png', 10000, '김치랑 고기의 익힘정도가 이븐해요.');
// SQL;
        
//         $pdo->exec($insertFoodsSql);
        
        echo json_encode(['success'=> true, 'message'=> '🎉 데이터 초기 세팅(INSERT)이 성공적으로 완료되었습니다!']);
    } else {
        echo json_encode(['success'=> true, 'message'=> "ℹ️ 이미 초기 데이터($count 건)가 존재하여 INSERT를 건너뜁니다."]);
    }

} catch (\PDOException $e) {
    // db.php의 포맷에 맞추어 에러 발생 시 JSON 형태로 에러 메시지를 반환합니다.
    echo json_encode(['success'=> false, 'message'=> '초기 데이터 세팅 실패: '. $e->getMessage()]);
}
?>
