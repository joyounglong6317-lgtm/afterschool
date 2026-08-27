-- ============================================
-- 기능장 자격증 취합·판별 시스템 (PHP+MySQL)
-- ============================================
CREATE DATABASE IF NOT EXISTS hanil_cert
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hanil_cert;

-- 사용자 (학생/담임/관리자)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role ENUM('student','teacher','admin') NOT NULL,
  login_id VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  name VARCHAR(50) NOT NULL,
  grade TINYINT NULL COMMENT '학년 (학생용)',
  class_no TINYINT NULL COMMENT '반 (학생용)',
  student_no TINYINT NULL COMMENT '번호 (학생용)',
  status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  enrollment_date DATE NULL COMMENT '입학일 (재학중 취득 자동판정 기준, 필수 2종은 예외)',
  failed_login_count TINYINT NOT NULL DEFAULT 0,
  locked_until DATETIME NULL,
  last_login_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_class (role, grade, class_no, student_no)
) ENGINE=InnoDB;

-- 담임 배정 (학년-반 ↔ 교사 계정)
CREATE TABLE teacher_assignments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grade TINYINT NOT NULL,
  class_no TINYINT NOT NULL,
  teacher_id INT NOT NULL,
  UNIQUE KEY uq_class (grade, class_no),
  FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- 전교 학생 명단 (자가입회 시 이름 대조 + 미가입자 파악용)
CREATE TABLE roster (
  id INT AUTO_INCREMENT PRIMARY KEY,
  grade TINYINT NOT NULL,
  class_no TINYINT NOT NULL,
  student_no TINYINT NOT NULL,
  name VARCHAR(50) NOT NULL,
  UNIQUE KEY uq_roster (grade, class_no, student_no)
) ENGINE=InnoDB;

-- 학년별 반 개수 설정
CREATE TABLE ban_counts (
  grade TINYINT PRIMARY KEY,
  class_count TINYINT NOT NULL DEFAULT 10
) ENGINE=InnoDB;
INSERT INTO ban_counts (grade, class_count) VALUES (1,10),(2,10),(3,10);

-- 취합 주기 (매학기)
CREATE TABLE cycles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  school_year YEAR NOT NULL,
  semester ENUM('1','2') NOT NULL,
  is_current TINYINT(1) NOT NULL DEFAULT 0,
  deadline_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_cycle (school_year, semester)
) ENGINE=InnoDB;

-- 제출 데이터 (학생 1명 × 학기 1개 = 1행)
CREATE TABLE submissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  cycle_id INT NOT NULL,
  counts_json TEXT NOT NULL DEFAULT '{}' COMMENT '항목id => 개수',
  national TINYINT(1) NOT NULL DEFAULT 0 COMMENT '전국기능경기대회 참가',
  gyeongnam INT NULL COMMENT '경남기능경기대회 순위',
  past_winner TINYINT(1) NOT NULL DEFAULT 0 COMMENT '이전 학기 기능장 수상',
  other_cert_name VARCHAR(150) NULL,
  confirmed_enrolled TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',
  teacher_comment VARCHAR(255) NULL,
  reviewer_id INT NULL,
  reviewed_at DATETIME NULL,
  submitted_at DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_student_cycle (student_id, cycle_id),
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE CASCADE,
  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- 학교 지정 허용 IP
CREATE TABLE allowed_ips (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ip_address VARCHAR(45) NOT NULL,
  description VARCHAR(100),
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 로그인 로그
CREATE TABLE login_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  login_id_attempt VARCHAR(50) NULL,
  user_id INT NULL,
  ip_address VARCHAR(45) NOT NULL,
  success TINYINT(1) NOT NULL,
  reason VARCHAR(100) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- 최초 취합 주기 하나 생성 (설치 후 관리자 화면에서 변경 가능)
INSERT INTO cycles (school_year, semester, is_current, deadline_at)
VALUES (YEAR(CURDATE()), IF(MONTH(CURDATE()) BETWEEN 3 AND 8, '1', '2'), 1, NULL);
