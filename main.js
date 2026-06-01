// main.js

// Flask 서버 IP 설정
const FLASK_API_URL = 'http://10.1.53.106:5000/api/recommend';

let previousFood = null;
let rejectedFoods = [];
const maxPrice = 20000;

window.currentFood = null;
window.currentStore = null;

window.addEventListener('DOMContentLoaded', () => {
    // IS_LOGGED_IN_SERVER 등은 index.php에서 미리 선언해둔 글로벌 변수를 사용합니다.
    if(IS_LOGGED_IN_SERVER) {
        document.getElementById('auth-zone').classList.add('hidden');
        document.getElementById('main-page').classList.remove('hidden');
        document.getElementById('user-welcome').innerText = `${SERVER_USER_EMAIL}님 환영합니다!`;
        
        requestAiRecommendation(true); 
    }

    const feedbackInput = document.getElementById('feedback-input');
    if (feedbackInput) {
        feedbackInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') requestAiRecommendation();
        });
    }
});

function switchAuthView(viewName) { 
    document.getElementById('login-box').classList.add('hidden');
    document.getElementById('register-box').classList.add('hidden');
    document.getElementById('find-id-box').classList.add('hidden');
    document.getElementById('find-pw-box').classList.add('hidden');

    if(viewName === 'login') document.getElementById('login-box').classList.remove('hidden');
    if(viewName === 'register') document.getElementById('register-box').classList.remove('hidden');
    if(viewName === 'find-id') document.getElementById('find-id-box').classList.remove('hidden');
    if(viewName === 'find-pw') document.getElementById('find-pw-box').classList.remove('hidden');
}

async function attemptFindId() { 
    const gender = document.getElementById('find-id-gender').value;
    const age = document.getElementById('find-id-age').value;
    if(!gender || !age) { alert('성별과 태어난 년도를 입력해주세요.'); return; }

    const response = await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'find_id', gender: gender, age: age })
    });
    const res = await response.json();
    alert(res.message);
    if(res.success) { switchAuthView('login'); }
}

async function attemptFindPw() { 
    const email = document.getElementById('find-pw-email').value;
    const age = document.getElementById('find-pw-age').value;
    if(!email || !age) { alert('이메일과 태어난 년도를 입력해주세요.'); return; }

    const response = await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'find_pw', email: email, age: age })
    });
    const res = await response.json();
    alert(res.message);
}

async function attemptLogin() { 
    const email = document.getElementById('login-email').value;
    const pw = document.getElementById('login-pw').value;
    if(!email || !pw) { alert('이메일과 비밀번호를 입력해주세요.'); return; }

    const response = await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'login', email: email, password: pw })
    });
    const res = await response.json();
    
    if(res.success) { location.reload(); } 
    else { alert(res.message); }
}

async function attemptRegister() { 
    const typeRadio = document.querySelector('input[name="reg-type"]:checked');
    const userType = typeRadio ? typeRadio.value : null;
    const email = document.getElementById('reg-email').value.trim();
    const pw = document.getElementById('reg-pw').value;
    const gender = document.getElementById('reg-gender').value;
    const age = document.getElementById('reg-age').value;

    if(!userType) { alert('학생인지 직원인지 선택해주세요.'); return; }
    if(!email || !pw || !gender || !age) { alert('모든 항목을 입력해주세요.'); return; }

    const response = await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'register', user_type: userType, email: email, password: pw, gender: gender, age: age })
    });
    const res = await response.json();
    alert(res.message);
    if(res.success) { switchAuthView('login'); }
}

async function logout() { 
    await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' }) 
    });
    location.reload();
}

function toggleChangePwView(show) { 
    const zone = document.getElementById('change-pw-zone');
    if(show) { zone.classList.remove('hidden'); zone.style.display = 'flex'; } 
    else { zone.classList.add('hidden'); zone.style.display = 'none'; }
}

async function attemptChangePw() { 
    const newPw = document.getElementById('new-pw-input').value;
    const confirmPw = document.getElementById('new-pw-confirm').value;
    
    if(!newPw || !confirmPw) { alert('비밀번호를 입력해주세요.'); return; }
    if(newPw !== confirmPw) { alert('비밀번호가 일치하지 않습니다.'); return; }

    const response = await fetch('auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'change_pw', new_password: newPw })
    });
    const res = await response.json();
    
    alert(res.message);
    if(res.success) { 
        toggleChangePwView(false);
        document.getElementById('new-pw-input').value = '';
        document.getElementById('new-pw-confirm').value = '';
    }
}

// main.js 내부의 기존 appendChat 함수를 이걸로 교체!
function appendChat(sender, text) {
    const chatLog = document.getElementById('ai-chat-log');
    const msgDiv = document.createElement('div');
    
    // 누가 보냈는지에 따라 클래스(디자인) 다르게 적용
    if (sender === 'user') {
        msgDiv.className = 'chat-msg chat-user';
    } else {
        msgDiv.className = 'chat-msg chat-bot';
    }
    
    msgDiv.innerHTML = text; 
    
    chatLog.appendChild(msgDiv);
    
    // 새로운 메시지가 추가되면 스크롤을 맨 아래로 부드럽게 내림
    chatLog.scrollTo({
        top: chatLog.scrollHeight,
        behavior: 'smooth'
    });
}

function playLotteryAnimation() {
    const targetBox = document.getElementById('food-card-target');
    const emojis = ['🍔', '🍕', '🍣', '🥩', '🍗', '🍜', '🍱'];
    let ballsHtml = '';
    
    for(let i = 0; i < 7; i++) {
        const tx1 = (Math.random() * 200 - 100) + 'px';
        const ty1 = (Math.random() * 100 - 50) + 'px';
        const tx2 = (Math.random() * 200 - 100) + 'px';
        const ty2 = (Math.random() * 100 - 50) + 'px';
        const tx3 = (Math.random() * 200 - 100) + 'px';
        const ty3 = (Math.random() * 100 - 50) + 'px';
        const duration = (Math.random() * 0.5 + 0.5) + 's'; 
        
        ballsHtml += `
            <div class="lottery-ball" style="
                --tx1: ${tx1}; --ty1: ${ty1};
                --tx2: ${tx2}; --ty2: ${ty2};
                --tx3: ${tx3}; --ty3: ${ty3};
                animation: flyAround ${duration} infinite alternate ease-in-out;
            ">${emojis[i]}</div>
        `;
    }

    document.getElementById('left-panel').classList.remove('active');
    document.getElementById('right-panel').classList.remove('active');

    // 💡 수정된 부분: 
    // 1. lottery-machine의 높이를 메뉴 사진과 동일한 350px로 고정했습니다.
    // 2. food-info 영역의 높이도 완성된 텍스트 크기와 비슷하게 170px로 고정하여 흔들림을 방지했습니다.
    targetBox.innerHTML = `
        <div class="lottery-machine" style="height: 350px; width: 100%; position: relative; background: #fafafa; overflow: hidden; border-bottom: 1px solid #eee;">
            ${ballsHtml}
        </div>
        <div class="food-info" style="padding: 25px; height: 170px; display: flex; align-items: center; justify-content: center; box-sizing: border-box;">
            <h2 class="food-title" style="color:#ff6b6b; margin: 0;">메뉴 추첨 중... 🎲</h2>
        </div>
    `;
}

async function requestAiRecommendation(isInitial = false) {
    const feedbackInput = document.getElementById('feedback-input');
    let feedback = null;

    if (!isInitial) {
        feedback = feedbackInput.value.trim();
        if(!feedback) {
            appendChat('user', '(아무 말 없이 다른 메뉴 추천받기)');
        } else {
            appendChat('user', feedback);
        }
        feedbackInput.value = ''; 
    }

    appendChat('bot', '가장 완벽한 메뉴를 검색 중입니다... 🔍');

    playLotteryAnimation();

    try {
        const response = await fetch(FLASK_API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                user_feedback: isInitial ? null : feedback,
                previous_food: previousFood,
                rejected_foods: rejectedFoods,
                max_price: maxPrice
            })
        });

        const data = await response.json();

        await new Promise(resolve => setTimeout(resolve, 1500)); 

        if (data.status === 'success' || data.status === 'no_more') {
            if (previousFood && !rejectedFoods.includes(previousFood) && previousFood !== data.food) {
                rejectedFoods.push(previousFood);
            }
            previousFood = data.food; 

            const store = storesData[data.store_id] || {
                store_name: "알 수 없는 가게(DB 누락)",
                location: "",
                ggultip: "가게 꿀팁 정보가 없습니다.",
                store_menu_url: ""
            };

            window.currentFood = {
                food_name: data.food,
                price: data.price,
                food_image_url: data.food_image_url,
                recommend_reason: data.recommend_reason,
                ai_generated_tags: data.tags
            };
            window.currentStore = store;

            renderFoodCard();
            syncSidePanelsData(); 

            if (data.status === 'success') {
                // 💡 폭죽 터트리기 코드 추가!
                confetti({
                    particleCount: 300, // 폭죽 조각 개수
                    spread: 150,         // 퍼지는 각도
                    origin: { y: 0.7 }, // 폭죽이 터지는 시작점 (화면 중간 살짝 아래)
                    colors: ['#ff6b6b', '#ffd93d', '#6bcb77', '#4d96ff'], // 폭죽 색상

                    scalar: 3,          // 💡 [핵심] 입자 크기를 기본값(1)의 2배로 키웁니다! (1.5 ~ 2.5 사이 추천)
                    startVelocity: 75,  // 입자가 커진 만큼 조금 더 힘차게 뿜어져 나오도록 속도 업
                    gravity: 0.8        // 덩치가 커졌으니 살짝 무게감 있게 떨어지도록 중력 설정
                });

                const tagList = data.tags.split(',').map(tag => `#${tag.trim()}`).join(' ');
                appendChat('bot', `💡 오늘의 추천은 <b>[${store.store_name}]</b>의 <b>[${data.food}]</b>입니다! (${Number(data.price).toLocaleString()}원)<br><span style="font-size:13px; color:#555;">${tagList}</span>`);
            } else {
                appendChat('bot', `❌ 조건에 맞는 다른 메뉴가 없어 현재 메뉴를 유지합니다.<br>💡 <b>${data.food}</b>`);
            }

        } else if (data.status === 'blocked') {
            appendChat('bot', `🚨 ${data.reason}`);
        }
    } catch (error) {
        appendChat('bot', '🚨 AI 서버와 통신할 수 없습니다. Flask 서버가 켜져 있는지 확인해 주세요.');
        console.error(error);
        
        const targetBox = document.getElementById('food-card-target');
        targetBox.innerHTML = `<h2 style="color:red; text-align:center;">통신 오류 발생 😢</h2>`;
    }
}

function renderFoodCard() {
    if(!window.currentFood || !window.currentStore) return;
    
    const food = window.currentFood;
    const store = window.currentStore;
    let cleanImageUrl = food.food_image_url.replace(/['"]/g, '').trim();
    cleanImageUrl = cleanImageUrl.replace(/^\//, ''); 
    
    const fullFoodImageUrl = S3_BASE_URL + cleanImageUrl;

    const targetBox = document.getElementById('food-card-target');
    
    targetBox.innerHTML = `
        <div class="unfold-wrapper">
            <img class="unfold-image" src="${fullFoodImageUrl}" alt="${food.food_name}" onerror="this.src='https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=600&q=80'">
        </div>
        <div class="food-info fade-in-text">
            <h2 class="food-title">${store.store_name}<br>추천 메뉴 : ${food.food_name}</h2>
            <div class="food-price">${Number(food.price).toLocaleString()} 원</div>
            <span class="click-hint">🔍 메뉴 사진을 클릭하면 좌우로 상세 정보가 열립니다!</span>
        </div>
    `;
}

function syncSidePanelsData() { 
    if(!window.currentFood || !window.currentStore) return;
    const store = window.currentStore;
    const food = window.currentFood;

    document.getElementById('board-title').innerText = `📋 ${store.store_name} - 메뉴판`;
    document.getElementById('board-img').src = S3_BASE_URL + store.store_menu_url;
    document.getElementById('store-title').innerText = store.store_name;
    
    const urlElem = document.getElementById('store-url');
    urlElem.innerText = `${store.store_name} 바로가기`; 
    let linkUrl = store.location; 

    if (linkUrl && linkUrl.trim() !== "") {
        if (!linkUrl.startsWith('http')) { linkUrl = 'https://' + linkUrl; }
        urlElem.href = linkUrl;
        urlElem.onclick = null; 
    } else {
        urlElem.href = "#";
        urlElem.onclick = function(e) {
            e.preventDefault(); 
            alert("아직 등록된 지도 링크가 없습니다.");
        };
    }
    
    document.getElementById('ai-reason').innerHTML = `<strong>💡 추천 이유:</strong><br>${food.recommend_reason}`;
    document.getElementById('store-notes').innerHTML = `<strong>🍯 꿀팁:</strong><br>${store.ggultip}`;
}

function toggleSidePanels() {
    const leftPanel = document.getElementById('left-panel');
    const rightPanel = document.getElementById('right-panel');

    if(leftPanel.classList.contains('active')) {
        leftPanel.classList.remove('active');
        rightPanel.classList.remove('active');
    } else {
        syncSidePanelsData();
        leftPanel.classList.add('active');
        rightPanel.classList.add('active');
    }
}

document.getElementById('board-img').addEventListener('click', function() {
    const zoomModal = document.getElementById('image-zoom-modal');
    const zoomedImg = document.getElementById('zoomed-img');
    zoomedImg.src = this.src; 
    zoomModal.classList.remove('hidden'); 
});

function closeZoomModal() {
    document.getElementById('image-zoom-modal').classList.add('hidden');
}