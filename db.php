<?php
// S3 기본 주소를 상수로 선언
define('S3_BASE_URL', 'https://cdn.whatfood.today/wft_food_image/');

// db.php - 로컬 PC 테스트용 설정
$host = '127.0.0.1';       // 로컬 호스트 IP (또는 'localhost')
$port = '2102';            // 💡 MariaDB 포트 번호 추가
$user = 'root';            // 로컬 MariaDB 기본 관리자 아이디
$pass = '1234'; // MariaDB 설치할 때 설정한 비밀번호
$db   = 'wft';   // users 테이블을 생성한 DB 이름
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);

    // 데이터베이스 생성 (없을 경우에만 안전하게 생성)
    $createDbSql = "CREATE DATABASE IF NOT EXISTS `$db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci";
    $pdo->exec($createDbSql);

    // 방금 생성한(또는 이미 있는) 데이터베이스 사용 선언
    $pdo->exec("USE `$db`");

    // 테이블이 없을 때만 자동 생성하는 쿼리문 실행
    $createTableSql = "CREATE TABLE IF NOT EXISTS customers (
        customer_id INT AUTO_INCREMENT PRIMARY KEY,
        user_type ENUM('학생', '직원') NOT NULL,
        email VARCHAR(255) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        gender ENUM('남', '녀') NOT NULL,
        age INT NOT NULL
    );";
    
    $pdo->exec($createTableSql);
    
} catch (\PDOException $e) {
    // 로컬 디버깅을 위해 연결 실패 시 에러 메시지를 브라우저나 콘솔에 출력합니다.
    echo json_encode(['success' => false, 'message' => '로컬 DB 연결 실패: ' . $e->getMessage()]);
    exit;
}
?>