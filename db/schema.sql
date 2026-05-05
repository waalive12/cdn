-- Cloudflare DNS 管理系统数据库结构

CREATE TABLE IF NOT EXISTS admin_users (
  id INT PRIMARY KEY AUTO_INCREMENT,
  username VARCHAR(50) UNIQUE NOT NULL,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cloudflare_accounts (
  id INT PRIMARY KEY AUTO_INCREMENT,
  api_token VARCHAR(255) NOT NULL,
  zone_id VARCHAR(255) NOT NULL,
  zone_name VARCHAR(255) NOT NULL,
  api_email VARCHAR(255),
  api_key VARCHAR(255),
  verified TINYINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_zone_id (zone_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS custom_hostnames (
  id INT PRIMARY KEY AUTO_INCREMENT,
  cf_account_id INT NOT NULL,
  hostname VARCHAR(255) NOT NULL,
  mode ENUM('random', 'fixed') DEFAULT 'random',
  prefix VARCHAR(100),
  custom_origin VARCHAR(255),
  ssl_status VARCHAR(50),
  dcv_token LONGTEXT,
  verification_status VARCHAR(50) DEFAULT 'pending',
  is_active TINYINT DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (cf_account_id) REFERENCES cloudflare_accounts(id) ON DELETE CASCADE,
  UNIQUE KEY uk_hostname (hostname)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS generated_domains (
  id INT PRIMARY KEY AUTO_INCREMENT,
  hostname_id INT NOT NULL,
  subdomain VARCHAR(255) NOT NULL,
  full_domain VARCHAR(255) NOT NULL,
  txt_record LONGTEXT,
  cname_target VARCHAR(255) DEFAULT 'dns.ptdns360.com',
  status ENUM('pending', 'active', 'failed') DEFAULT 'pending',
  last_check TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (hostname_id) REFERENCES custom_hostnames(id) ON DELETE CASCADE,
  UNIQUE KEY uk_full_domain (full_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS dns_check_logs (
  id INT PRIMARY KEY AUTO_INCREMENT,
  domain_id INT NOT NULL,
  check_type ENUM('cname', 'txt', 'ns') DEFAULT 'cname',
  expected_value LONGTEXT,
  actual_value LONGTEXT,
  result ENUM('success', 'failed', 'timeout') DEFAULT 'failed',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (domain_id) REFERENCES generated_domains(id) ON DELETE CASCADE,
  KEY idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_cf_account_id ON custom_hostnames(cf_account_id);
CREATE INDEX idx_hostname_id ON generated_domains(hostname_id);
CREATE INDEX idx_status ON generated_domains(status);
CREATE INDEX idx_domain_id ON dns_check_logs(domain_id);
