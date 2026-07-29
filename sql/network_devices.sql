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

-- Multiple photos per audit result (audit_results only stores one img_dir).
-- Also auto-created by the middleware on startup.
CREATE TABLE IF NOT EXISTS audit_result_images (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    audit_result_id INT NOT NULL,
    img_dir         VARCHAR(255) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY audit_result_id (audit_result_id),
    CONSTRAINT fk_ari_audit_result FOREIGN KEY (audit_result_id)
        REFERENCES audit_results (id) ON DELETE CASCADE
);
