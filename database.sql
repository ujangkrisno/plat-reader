-- Database Plat Reader
CREATE DATABASE IF NOT EXISTS plat_reader;
USE plat_reader;

CREATE TABLE cameras (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    url VARCHAR(500) NOT NULL,
    lokasi VARCHAR(200) DEFAULT '',
    aktif TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE plat_nomor (
    id INT AUTO_INCREMENT PRIMARY KEY,
    camera_id INT,
    plat VARCHAR(20) NOT NULL,
    gambar VARCHAR(255) DEFAULT '',
    confidence DECIMAL(5,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE CASCADE
);

CREATE INDEX idx_plat ON plat_nomor(plat);
CREATE INDEX idx_tgl ON plat_nomor(created_at);

-- Sample camera (sesuaikan dengan kamera/HPmu)
INSERT INTO cameras (nama, url, lokasi) VALUES
('CCTV Depan', 'rtsp://admin:12345@192.168.1.100:554/stream1', 'Pintu Masuk'),
('HP Kamera', 'http://192.168.1.20:8080/video', 'Parkir Depan');
