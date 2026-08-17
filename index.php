<?php
session_start();
require_once 'db.php';

// --- AÇÕES DO BACKEND (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Registro
    if ($action === 'register') {
        $nome = $_POST['nome'];
        $email = $_POST['email'];
        $senha = password_hash($_POST['senha'], PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
        $stmt->execute([$nome, $email, $senha]);
        $_SESSION['user_id'] = $pdo->lastInsertId();
        header('Location: index.php');
        exit;
    }

    // Login
    if ($action === 'login') {
        $email = $_POST['email'];
        $senha = $_POST['senha'];

        $stmt = $pdo->prepare("SELECT * FROM usuarios WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            $_SESSION['user_id'] = $user['id'];
        }
        header('Location: index.php');
        exit;
    }

    // Adicionar Atleta
    if ($action === 'add_athlete' && isset($_SESSION['user_id'])) {
        $nome = $_POST['nome'];
        $numero = $_POST['numero'];
        $posicao = $_POST['posicao'];
        $foto_url = $_POST['foto_url'] ?: 'https://via.placeholder.com/150';
        $nascimento = $_POST['data_nascimento'] ?: null;

        $stmt = $pdo->prepare("INSERT INTO atletas (usuario_id, nome, numero, posicao, foto_url, data_nascimento) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $nome, $numero, $posicao, $foto_url, $nascimento]);
        header('Location: index.php');
        exit;
    }

    // Registrar Lesão
    if ($action === 'add_injury' && isset($_SESSION['user_id'])) {
        $atleta_id = $_POST['atleta_id'];
        $tipo = $_POST['tipo'];
        $descricao = $_POST['descricao'];
        $data = $_POST['data'];

        $stmt = $pdo->prepare("INSERT INTO lesoes (atleta_id, tipo, descricao, data_lesao) VALUES (?, ?, ?, ?)");
        $stmt->execute([$atleta_id, $tipo, $descricao, $data]);
        header('Location: index.php?atleta=' . $atleta_id);
        exit;
    }
}

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

$isLoggedIn = isset($_SESSION['user_id']);
$view = $_GET['view'] ?? ($isLoggedIn ? 'dashboard' : 'login');
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JIFC ANALYTICS</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<?php if (!$isLoggedIn): ?>
    <!-- TELA DE LOGIN / REGISTRO -->
    <div class="auth-container">
        <div class="auth-banner">
            <h1>JIFC<br>ANALYTICS</h1>
            <p>Análise de Performance de Atletas de Futsal</p>
        </div>
        <div class="auth-form-container">
            <?php if ($view === 'register'): ?>
                <div class="auth-box">
                    <h2>CRIAR CONTA</h2>
                    <p>Cadastre-se para começar</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="register">
                        <div class="form-group">
                            <label>NOME COMPLETO</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>EMAIL</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>SENHA</label>
                            <input type="password" name="senha" class="form-control" required>
                        </div>
                        <button type="submit" class="btn-primary">CRIAR CONTA</button>
                    </form>
                    <a href="index.php?view=login" class="auth-link">Já tem conta? Entrar</a>
                </div>
            <?php else: ?>
                <div class="auth-box">
                    <h2>ENTRAR</h2>
                    <p>Acesse sua conta para gerenciar atletas</p>
                    <form method="POST">
                        <input type="hidden" name="action" value="login">
                        <div class="form-group">
                            <label>EMAIL</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>SENHA</label>
                            <input type="password" name="senha" class="form-control" required>
                        </div>
                        <button type="submit" class="btn-primary">ENTRAR</button>
                    </form>
                    <a href="index.php?view=register" class="auth-link">Não tem conta? Criar conta</a>
                </div>
            <?php endif; ?>
        </div>
    </div>

<?php else: ?>
    <!-- DASHBOARD PRINCIPAL -->
    <?php
        // Busca Totais do Usuário
        $stmt = $pdo->prepare("SELECT COUNT(*) as total_atletas, SUM(gols) as total_gols, SUM(assistencias) as total_ass, SUM(cartoes_amarelos + cartoes_vermelhos) as total_cartoes FROM atletas WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $stats = $stmt->fetch();

        // Busca Lista de Atletas
        $stmt = $pdo->prepare("SELECT * FROM atletas WHERE usuario_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $atletas = $stmt->fetchAll();
    ?>

    <header class="header">
        <div>
            <h2>JIFC ANALYTICS</h2>
            <small style="color: #888;">Dashboard de Performance</small>
        </div>
        <a href="?logout=1" style="color: #ef4444; text-decoration: none;">Sair</a>
    </header>

    <section class="stats-grid">
        <div class="stat-card">
            <h3>ATLETAS</h3>
            <span><?= $stats['total_atletas'] ?: 0 ?></span>
        </div>
        <div class="stat-card">
            <h3>TOTAL GOLS</h3>
            <span><?= $stats['total_gols'] ?: 0 ?></span>
        </div>
        <div class="stat-card">
            <h3>ASSISTÊNCIAS</h3>
            <span><?= $stats['total_ass'] ?: 0 ?></span>
        </div>
        <div class="stat-card">
            <h3>CARTÕES</h3>
            <span><?= $stats['total_cartoes'] ?: 0 ?></span>
        </div>
    </section>

    <section class="content-section">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h3>ELENCO</h3>
            <button class="btn-primary" style="width: auto; padding: 0.6rem 1.2rem;" onclick="openModal('modalAthlete')">+ ADICIONAR ATLETA</button>
        </div>

        <div class="cards-grid">
            <?php foreach ($atletas as $atleta): ?>
                <a href="atleta.php?id=<?= $atleta['id'] ?>" class="atleta-card-link" style="text-decoration: none; color: inherit;">
            <div class="atleta-card">
                <div class="atleta-foto-container">
                    <img src="<?= htmlspecialchars($atleta['foto_url'] ?: 'img/default.jpg') ?>" alt="Foto">
                    <span class="atleta-numero"><?= $atleta['numero'] ?></span>
                </div>
                <div class="atleta-info">
                    <h3><?= htmlspecialchars($atleta['nome']) ?></h3>
                    <p><?= htmlspecialchars($atleta['posicao']) ?></p>
                </div>
            </div>
        </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- MODAL NOVO ATLETA -->
    <div class="modal" id="modalAthlete">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('modalAthlete')">&times;</span>
            <h2 style="margin-bottom: 1.5rem;">NOVO ATLETA</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_athlete">
                <div class="form-group">
                    <label>NOME</label>
                    <input type="text" name="nome" class="form-control" required>
                </div>
                <div style="display: flex; gap: 1rem;">
                    <div class="form-group" style="flex: 1;">
                        <label>NÚMERO</label>
                        <input type="number" name="numero" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>POSIÇÃO</label>
                        <select name="posicao" class="form-control" required>
                            <option value="Goleiro">Goleiro</option>
                            <option value="Fixo">Fixo</option>
                            <option value="Ala">Ala</option>
                            <option value="Pivô">Pivô</option>
                        </select>
                        
                    </div>
                </div>
                <div class="form-group">
                    <label>URL DA FOTO (OPCIONAL)</label>
                    <input type="url" name="foto_url" placeholder="https://" class="form-control">
                </div>
                <div class="form-group">
                    <label>DATA DE NASCIMENTO (OPCIONAL)</label>
                    <input type="date" name="data_nascimento" class="form-control">
                </div>
                <button type="submit" class="btn-primary">ADICIONAR</button>
            </form>
        </div>
    </div>
<?php endif; ?>

<script>
    function openModal(id) {
        document.getElementById(id).style.display = 'flex';
    }

    function closeModal(id) {
        document.getElementById(id).style.display = 'none';
    }

    // Fecha o modal se clicar fora dele
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>   