-- Network devices (routers / switches) entered manually from the Android app.
-- The middleware also creates this table automatically on startup
-- (CREATE TABLE IF NOT EXISTS), so running this by hand is optional.

CREATE TABLE IF NOT EXISTS network_devices (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    device_type ENUM('Router','Switch') NOT NULL,
    brand       VARCHAR(100) NULL,
    model       VARCHAR(100) NULL,
    ip_address  VARCHAR(45)  NULL,
    mac_address VARCHAR(17)  NULL,
    location_id INT          NULL,
    audit_id    INT          NULL,
    created_by  VARCHAR(100) NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
);
