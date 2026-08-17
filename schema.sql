CREATE DATABASE IF NOT EXISTS jifc_analytics;
USE jifc_analytics;

CREATE TABLE usuarios (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    senha VARCHAR(255) NOT NULL,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE atletas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    nome VARCHAR(100) NOT NULL,
    numero INT NOT NULL,
    posicao VARCHAR(50) NOT NULL,
    foto_url VARCHAR(255) DEFAULT NULL,
    data_nascimento DATE DEFAULT NULL,
    gols INT DEFAULT 0,
    assistencias INT DEFAULT 0,
    cartoes_amarelos INT DEFAULT 0,
    cartoes_vermelhos INT DEFAULT 0,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE
);

CREATE TABLE lesoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atleta_id INT NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    descricao TEXT DEFAULT NULL,
    data_lesao DATE NOT NULL,
    FOREIGN KEY (atleta_id) REFERENCES atletas(id) ON DELETE CASCADE
);
CREATE TABLE IF NOT EXISTS lesoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atleta_id INT NOT NULL,
    tipo VARCHAR(100) NOT NULL,
    descricao TEXT,
    data_inicio DATE NOT NULL,
    previsao_retorno DATE,
    status ENUM('Em Tratamento', 'Recuperado') DEFAULT 'Em Tratamento',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (atleta_id) REFERENCES atletas(id) ON DELETE CASCADE
);