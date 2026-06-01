<?php 
session_start(); 
require_once 'db.php'; // db.php를 불러오기

// 로그인 세션이 이미 존재하면 프론트엔드에 상태를 넘겨주기 위한 처리
$isLoggedIn = isset($_SESSION['customer_id']) ? 'true' : 'false';
$userEmail = isset($_SESSION['email']) ? $_SESSION['email'] : '';

// 1. DB에서 진짜 음식 데이터 가져오기
$stmtFoods = $pdo->query("SELECT food_id, store_id, food_name, food_image_url, price, recommend_reason, ai_generated_tags FROM foods");
$realFoods = $stmtFoods->fetchAll(PDO::FETCH_ASSOC);

// 2. DB에서 진짜 가게 데이터 가져오기 (JS에서 쓰기 편하게 store_id를 키값으로 정리)
$stmtStores = $pdo->query("SELECT * FROM stores");
$stores = $stmtStores->fetchAll(PDO::FETCH_ASSOC);
$realStores = [];
foreach ($stores as $store) {
    $realStores[$store['store_id']] = $store;
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>whatfood.today - 단 하나의 메뉴 추천</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="animation.css">
    
    <link rel="icon" href="logo_bg_remove.png" type="image/png">

    <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.6.0/dist/confetti.browser.min.js"></script>
</head>
<body>

    <div id="auth-zone" class="auth-container">
        <div id="login-box" class="login-box">
            <h1>whatfood</h1>
            <p>오늘 뭐 먹지?</p>
            <input type="email" id="login-email" placeholder="이메일 주소" required>
            <input type="password" id="login-pw" placeholder="비밀번호" required>
            <button onclick="attemptLogin()">로그인</button>
            <div class="login-links">
                <a onclick="switchAuthView('register')">회원가입</a> | 
                <a onclick="switchAuthView('find-id')">아이디 찾기</a> |
                <a onclick="switchAuthView('find-pw')">비밀번호 찾기</a>
            </div>
        </div>

        <div id="register-box" class="login-box hidden">
            <h1>회원가입</h1>
            <div style="display: flex; justify-content: center; gap: 40px; margin: 15px 0; color: #555; font-size: 15px;">
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <input type="radio" name="reg-type" value="학생" style="width: auto; margin: 0; padding: 0;"> 🎓 학생
                </label>
                <label style="cursor: pointer; display: flex; align-items: center; gap: 8px;">
                    <input type="radio" name="reg-type" value="직원" style="width: auto; margin: 0; padding: 0;"> 💼 직원
                </label>
            </div>
            <input type="email" id="reg-email" placeholder="이메일 주소" required>
            <input type="password" id="reg-pw" placeholder="비밀번호 (4자리 이상)" required>
            <select id="reg-gender" required>
                <option value="" disabled selected>성별 선택</option>
                <option value="남">남자</option>
                <option value="녀">여자</option>
            </select>
            <input type="number" id="reg-age" placeholder="태어난 년도 (숫자만)" min="1" max="150" required>
            <button onclick="attemptRegister()">가입하기</button>
            <div class="login-links"><a onclick="switchAuthView('login')">이전으로 돌아가기</a></div>
        </div>

        <div id="find-id-box" class="login-box hidden">
            <h1>아이디 찾기</h1>
            <h2>성별과 태어난 년도를 입력해 주세요</h2>
            <select id="find-id-gender" required>
                <option value="" disabled selected>성별 선택</option>
                <option value="남">남자</option>
                <option value="녀">여자</option>
            </select>
            <input type="number" id="find-id-age" placeholder="가입 시 입력한 태어난 년도" min="1" max="150" required>
            <button onclick="attemptFindId()">아이디 찾기</button>
            <div class="login-links"><a onclick="switchAuthView('login')">이전으로 돌아가기</a></div>
        </div>

        <div id="find-pw-box" class="login-box hidden">
            <h1>비밀번호 찾기</h1>
            <h2>가입한 이메일과 태어난 년도를 입력해 주세요</h2>
            <input type="email" id="find-pw-email" placeholder="이메일 주소" required>
            <input type="number" id="find-pw-age" placeholder="가입 시 입력한 태어난 년도" required>
            <button onclick="attemptFindPw()">비밀번호 초기화</button>
            <div class="login-links"><a onclick="switchAuthView('login')">이전으로 돌아가기</a></div>
        </div>
    </div>

    <div id="main-page" class="hidden">
        <header>
            <div class="logo-container">
                <img src="logo_bg_remove.png" alt="whatfood 로고" class="header-logo">
                <h1>whatfood.today</h1>
            </div>
            <div class="user-area">
                <span id="user-welcome" style="font-weight:bold; color: #555;"></span>
                <button onclick="toggleChangePwView(true)" style="border: 1px solid #ddd; background: white; border-radius: 5px;">비밀번호 변경</button>
                <button onclick="logout()" style="border: none; background: #ff6b6b; color: white; font-weight: bold;">로그아웃</button>
            </div>
        </header>

        <div class="workspace">
            <div class="interactive-showcase">
                <div id="left-panel" class="side-panel left-slide">
                    <div class="menu-board-section">
                        <h3 id="board-title">📋 가게 - 전체 메뉴판</h3>
                        <img id="board-img" src="" class="menu-board-img" alt="가게 메뉴판">
                    </div>
                </div>

                <div class="food-card-center">
                    <div id="food-card-target" class="food-card" onclick="toggleSidePanels()">
                        </div>
                </div>

                <div id="right-panel" class="side-panel right-slide">
                    <h2 id="store-title" class="store-title">가게 이름</h2>
                    <p class="store-loc">
                        📍 지도: <a id="store-url" href="#" target="_blank" style="font-weight: bold;"></a>
                    </p>
                    <div id="ai-reason" class="reason-box">추천 이유</div>
                    <div id="store-notes" class="store-notes">특이사항</div>
                </div>
            </div>

            <div class="chat-container">
                <div id="ai-chat-log" class="chat-log">
                    <div class="chat-msg chat-bot">
                        오늘 점심, 어떤 메뉴를 찾으시나요? 편하게 말씀해 주세요! 😋
                    </div>
                </div>
                <div class="chat-input-area">
                    <input type="text" id="feedback-input" placeholder="예: 매운 거 말고 단백질 많은 거, 2만원 이하 등">
                    <button onclick="requestAiRecommendation()">전송</button>
                </div>
            </div>
        </div>
    </div>

    <div id="change-pw-zone" class="hidden" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:999; display:flex; align-items:center; justify-content:center;">
        <div class="login-box" style="width:350px;">
            <h1>비밀번호 변경</h1>
            <p>새로 사용할 비밀번호를 입력하세요.</p>
            <input type="password" id="new-pw-input" placeholder="새 비밀번호" required>
            <input type="password" id="new-pw-confirm" placeholder="새 비밀번호 확인" required>
            <button onclick="attemptChangePw()">변경하기</button>
            <button onclick="toggleChangePwView(false)" style="background:#999; margin-top:5px;">취소</button>
        </div>
    </div>

    <div id="image-zoom-modal" class="hidden" onclick="closeZoomModal()" style="position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.8); z-index:9999; display:flex; align-items:center; justify-content:center; cursor:zoom-out;">
        <img id="zoomed-img" src="" style="max-width:90%; max-height:90%; object-fit:contain; border-radius:10px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);">
    </div>

    <script>
        const IS_LOGGED_IN_SERVER = <?php echo $isLoggedIn; ?>;
        const SERVER_USER_EMAIL = "<?php echo $userEmail; ?>";
        const S3_BASE_URL = "<?php echo defined('S3_BASE_URL') ? S3_BASE_URL : ''; ?>";
        const storesData = <?php echo json_encode($realStores); ?>;
        const foodsData = <?php echo json_encode($realFoods); ?>;
    </script>
    
    <script src="main.js"></script>
    
</body>
</html>