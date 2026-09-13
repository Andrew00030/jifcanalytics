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
        $foto_url = $_POST['foto_url'] ?: '';
        $nascimento = $_POST['data_nascimento'] ?: null;
        $foto_path = '';

        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $extensao = pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION);
            $extensao = strtolower($extensao);
            $extensoesPermitidas = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (in_array($extensao, $extensoesPermitidas)) {
                $nomeArquivo = 'atleta_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extensao;
                $destino = __DIR__ . '/uploads/atletas/' . $nomeArquivo;
                if (move_uploaded_file($_FILES['foto']['tmp_name'], $destino)) {
                    $foto_path = 'uploads/atletas/' . $nomeArquivo;
                }
            }
        }

        if (empty($foto_path)) {
            $foto_path = $foto_url ?: 'img/default.jpg';
        }

        $stmt = $pdo->prepare("INSERT INTO atletas (usuario_id, nome, numero, posicao, foto_url, data_nascimento) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $nome, $numero, $posicao, $foto_path, $nascimento]);
        header('Location: index.php');
        exit;
    }

    // Registrar Partida e Estatísticas por Atleta
    if ($action === 'add_match' && isset($_SESSION['user_id'])) {
        $adversario = trim($_POST['adversario'] ?? '');
        $data_partida = trim($_POST['data_partida'] ?? '');
        $local = trim($_POST['local'] ?? '');
        $competicao = trim($_POST['competicao'] ?? '');
        $resultado = trim($_POST['resultado'] ?? '');
        $observacoes = trim($_POST['observacoes'] ?? '');

        if (!empty($adversario) && !empty($data_partida)) {
            $stmt = $pdo->prepare("INSERT INTO partidas (usuario_id, adversario, data_partida, local, competicao, resultado, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $adversario,
                $data_partida,
                $local,
                $competicao,
                $resultado,
                $observacoes
            ]);

            $partida_id = $pdo->lastInsertId();

            $stmtAtletas = $pdo->prepare("SELECT id FROM atletas WHERE usuario_id = ? ORDER BY nome ASC");
            $stmtAtletas->execute([$_SESSION['user_id']]);
            $atletas = $stmtAtletas->fetchAll();

            foreach ($atletas as $atleta) {
                $atleta_id = (int)$atleta['id'];
                $minutos = (int)($_POST['minutos_' . $atleta_id] ?? 0);
                $gols = (int)($_POST['gols_' . $atleta_id] ?? 0);
                $assistencias = (int)($_POST['assistencias_' . $atleta_id] ?? 0);
                $cartoes_amarelos = (int)($_POST['cartoes_amarelos_' . $atleta_id] ?? 0);
                $cartoes_vermelhos = (int)($_POST['cartoes_vermelhos_' . $atleta_id] ?? 0);
                $avaliacao = isset($_POST['avaliacao_' . $atleta_id]) ? (float)($_POST['avaliacao_' . $atleta_id]) : 0;
                $observacoes_atleta = trim($_POST['observacoes_atleta_' . $atleta_id] ?? '');

                if ($minutos > 0 || $gols > 0 || $assistencias > 0 || $cartoes_amarelos > 0 || $cartoes_vermelhos > 0 || !empty($observacoes_atleta)) {
                    $stmtEst = $pdo->prepare("INSERT INTO estatisticas_partidas (partida_id, atleta_id, minutos_jogados, gols, assistencias, cartoes_amarelos, cartoes_vermelhos, avaliacao, observacoes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmtEst->execute([
                        $partida_id,
                        $atleta_id,
                        $minutos,
                        $gols,
                        $assistencias,
                        $cartoes_amarelos,
                        $cartoes_vermelhos,
                        $avaliacao,
                        $observacoes_atleta
                    ]);
                }
            }
        }

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

// Excluir atleta
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_athlete' && isset($_SESSION['user_id'])) {
    $atleta_id = (int)($_POST['atleta_id'] ?? 0);

    if ($atleta_id > 0) {
        $stmt = $pdo->prepare("SELECT id FROM atletas WHERE id = ? AND usuario_id = ?");
        $stmt->execute([$atleta_id, $_SESSION['user_id']]);
        $atleta = $stmt->fetch();

        if ($atleta) {
            $stmtDelete = $pdo->prepare("DELETE FROM atletas WHERE id = ? AND usuario_id = ?");
            $stmtDelete->execute([$atleta_id, $_SESSION['user_id']]);
        }
    }

    header('Location: index.php');
    exit;
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
        // Busca Totais do Usuário com base na tabela de estatísticas por partida
        $stmt = $pdo->prepare("SELECT
                (SELECT COUNT(*) FROM atletas WHERE usuario_id = ?) as total_atletas,
                (SELECT COALESCE(SUM(ep.gols), 0) FROM estatisticas_partidas ep JOIN atletas a ON a.id = ep.atleta_id WHERE a.usuario_id = ?) as total_gols,
                (SELECT COALESCE(SUM(ep.assistencias), 0) FROM estatisticas_partidas ep JOIN atletas a ON a.id = ep.atleta_id WHERE a.usuario_id = ?) as total_ass,
                (SELECT COALESCE(SUM(ep.cartoes_amarelos + ep.cartoes_vermelhos), 0) FROM estatisticas_partidas ep JOIN atletas a ON a.id = ep.atleta_id WHERE a.usuario_id = ?) as total_cartoes");
        $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
        $stats = $stmt->fetch();

        // Busca Lista de Atletas
        $stmt = $pdo->prepare("SELECT * FROM atletas WHERE usuario_id = ? ORDER BY nome ASC");
        $stmt->execute([$_SESSION['user_id']]);
        $atletas = $stmt->fetchAll();

        // Busca lista de partidas cadastradas
        $stmtPartidas = $pdo->prepare("SELECT * FROM partidas WHERE usuario_id = ? ORDER BY data_partida DESC");
        $stmtPartidas->execute([$_SESSION['user_id']]);
        $partidas = $stmtPartidas->fetchAll();
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
        <div class="section-toolbar">
            <div>
                <h3 class="section-title-dashboard">ELENCO</h3>
            </div>
            <div class="button-row">
                <button class="btn-primary" style="width: auto; padding: 0.6rem 1.2rem;" onclick="openModal('modalMatch')">+ CADASTRAR PARTIDA</button>
                <button class="btn-primary" style="width: auto; padding: 0.6rem 1.2rem;" onclick="openModal('modalAthlete')">+ ADICIONAR ATLETA</button>
            </div>
        </div>

        <div class="cards-grid">
            <?php foreach ($atletas as $atleta): ?>
                <div class="atleta-card-link-wrap">
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
                    <form method="POST" class="delete-athlete-form" onsubmit="return confirm('Deseja remover este atleta?');">
                        <input type="hidden" name="action" value="delete_athlete">
                        <input type="hidden" name="atleta_id" value="<?= $atleta['id'] ?>">
                        <button type="submit" class="delete-btn">×</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="content-section">
        <div class="section-toolbar compact">
            <div>
                <h3 class="section-title-dashboard">PARTIDAS CADASTRADAS</h3>
            </div>
        </div>

        <?php if (empty($partidas)): ?>
            <div class="empty-card">
                <p>Nenhuma partida cadastrada. Registre uma partida para iniciar o histórico.</p>
            </div>
        <?php else: ?>
            <div class="matches-grid">
                <?php foreach ($partidas as $partida): ?>
                    <div class="match-card">
                        <div class="match-header">
                            <span class="match-date"><?= date('d/m/Y', strtotime($partida['data_partida'])) ?></span>
                            <span class="match-result"><?= htmlspecialchars($partida['resultado'] ?: 'Resultado não informado') ?></span>
                        </div>
                        <div class="match-body">
                            <strong><?= htmlspecialchars($partida['adversario']) ?></strong>
                            <span><?= htmlspecialchars($partida['competicao'] ?: 'JIFC') ?></span>
                            <span><?= htmlspecialchars($partida['local'] ?: 'Local não informado') ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- MODAL CADASTRAR PARTIDA -->
    <div class="modal" id="modalMatch">
        <div class="modal-content modal-large">
            <span class="close-btn" onclick="closeModal('modalMatch')">&times;</span>
            <h2 style="margin-bottom: 1.5rem;">CADASTRAR PARTIDA</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_match">

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>ADVERSÁRIO</label>
                        <input type="text" name="adversario" class="form-control" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>DATA DA PARTIDA</label>
                        <input type="date" name="data_partida" class="form-control" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>LOCAL</label>
                        <input type="text" name="local" class="form-control" placeholder="Ginásio">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>COMPETIÇÃO</label>
                        <input type="text" name="competicao" class="form-control" value="JIFC">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>RESULTADO</label>
                        <input type="text" name="resultado" class="form-control" placeholder="Vitória / Derrota / Empate">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>OBSERVAÇÕES</label>
                        <textarea name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                </div>

                <div class="match-table-wrap">
                    <table class="match-table">
                        <thead>
                            <tr>
                                <th>ATLETA</th>
                                <th>MINUTOS</th>
                                <th>GOLS</th>
                                <th>ASSIST.</th>
                                <th>CA</th>
                                <th>CV</th>
                                <th>AVALIAÇÃO</th>
                                <th>OBS.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atletas as $atleta): ?>
                                <tr>
                                    <td class="match-player">
                                        <span><?= htmlspecialchars($atleta['nome']) ?></span>
                                        <small><?= htmlspecialchars($atleta['posicao']) ?></small>
                                    </td>
                                    <td><input type="number" name="minutos_<?= $atleta['id'] ?>" value="0" min="0" class="form-control small-input"></td>
                                    <td><input type="number" name="gols_<?= $atleta['id'] ?>" value="0" min="0" class="form-control small-input"></td>
                                    <td><input type="number" name="assistencias_<?= $atleta['id'] ?>" value="0" min="0" class="form-control small-input"></td>
                                    <td><input type="number" name="cartoes_amarelos_<?= $atleta['id'] ?>" value="0" min="0" class="form-control small-input"></td>
                                    <td><input type="number" name="cartoes_vermelhos_<?= $atleta['id'] ?>" value="0" min="0" class="form-control small-input"></td>
                                    <td><input type="number" name="avaliacao_<?= $atleta['id'] ?>" value="8" min="0" max="10" step="0.1" class="form-control small-input"></td>
                                    <td><textarea name="observacoes_atleta_<?= $atleta['id'] ?>" class="form-control" rows="2"></textarea></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn-primary" style="width: auto; padding: 0.8rem 1.5rem; margin-top: 1rem;">SALVAR PARTIDA</button>
            </form>
        </div>
    </div>

    <!-- MODAL NOVO ATLETA -->
    <div class="modal" id="modalAthlete">
        <div class="modal-content">
            <span class="close-btn" onclick="closeModal('modalAthlete')">&times;</span>
            <h2 style="margin-bottom: 1.5rem;">NOVO ATLETA</h2>
            <form method="POST" enctype="multipart/form-data">
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
                    <label>FOTOGRAFIA DO ATLETA</label>
                    <input type="file" name="foto" id="fotoCadastro" accept="image/*" class="form-control">
                    <div class="preview-box">
                        <img src="" id="previewCadastro" alt="Preview da foto" class="preview-image">
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