<?php
session_start();
require_once 'db.php';

$matchError = $_SESSION['match_error'] ?? '';
unset($_SESSION['match_error']);

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
        $partidaFutura = $data_partida > date('Y-m-d');
        $local = trim($_POST['local'] ?? '');
        $competicao = trim($_POST['competicao'] ?? '');
        $gols_time = $partidaFutura ? 0 : max(0, (int)($_POST['gols_time'] ?? 0));
        $gols_adversario = $partidaFutura ? 0 : max(0, (int)($_POST['gols_adversario'] ?? 0));
        $resultado = $gols_time . ' x ' . $gols_adversario;

        if (!empty($adversario) && !empty($data_partida)) {
            $stmtAtletas = $pdo->prepare("SELECT a.id FROM atletas a WHERE a.usuario_id = ? AND NOT EXISTS (SELECT 1 FROM lesoes l WHERE l.atleta_id = a.id AND l.status = 'Em Tratamento') ORDER BY a.nome ASC");
            $stmtAtletas->execute([$_SESSION['user_id']]);
            $atletas = $stmtAtletas->fetchAll();
            $suspensoesAtivas = obterSuspensoesAtivas($pdo, (int)$_SESSION['user_id']);
            $atletas = array_values(array_filter($atletas, static fn($atleta) => empty($suspensoesAtivas[(int)$atleta['id']]['suspenso'])));
            $estatisticasAtletas = [];
            $totalGolsAtletas = 0;

            foreach ($atletas as $atleta) {
                $atleta_id = (int)$atleta['id'];
                $gols = max(0, (int)($_POST['gols_' . $atleta_id] ?? 0));
                $estatisticasAtletas[$atleta_id] = [
                    'gols' => $gols,
                    'assistencias' => max(0, (int)($_POST['assistencias_' . $atleta_id] ?? 0)),
                    'cartoes_amarelos' => min(2, max(0, (int)($_POST['cartoes_amarelos_' . $atleta_id] ?? 0))),
                    'cartoes_vermelhos' => min(1, max(0, (int)($_POST['cartoes_vermelhos_' . $atleta_id] ?? 0))),
                ];
                $totalGolsAtletas += $gols;
            }

            if ($totalGolsAtletas > $gols_time) {
                $_SESSION['match_error'] = "Os gols dos atletas ({$totalGolsAtletas}) não podem ser maiores que os gols do time no placar ({$gols_time}).";
                header('Location: index.php');
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO partidas (usuario_id, adversario, data_partida, local, competicao, resultado) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $_SESSION['user_id'],
                $adversario,
                $data_partida,
                $local,
                $competicao,
                $resultado
            ]);

            $partida_id = $pdo->lastInsertId();

            foreach ($atletas as $atleta) {
                $atleta_id = (int)$atleta['id'];
                $gols = $estatisticasAtletas[$atleta_id]['gols'];
                $assistencias = $estatisticasAtletas[$atleta_id]['assistencias'];
                $cartoes_amarelos = $estatisticasAtletas[$atleta_id]['cartoes_amarelos'];
                $cartoes_vermelhos = $estatisticasAtletas[$atleta_id]['cartoes_vermelhos'];

                if ($gols > 0 || $assistencias > 0 || $cartoes_amarelos > 0 || $cartoes_vermelhos > 0) {
                    $stmtEst = $pdo->prepare("INSERT INTO estatisticas_partidas (partida_id, atleta_id, gols, assistencias, cartoes_amarelos, cartoes_vermelhos) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmtEst->execute([
                        $partida_id,
                        $atleta_id,
                        $gols,
                        $assistencias,
                        $cartoes_amarelos,
                        $cartoes_vermelhos
                    ]);
                }
            }
        }

        header('Location: index.php');
        exit;
    }

    // Editar partida e suas estatísticas
    if ($action === 'edit_match' && isset($_SESSION['user_id'])) {
        $partida_id = (int)($_POST['partida_id'] ?? 0);
        $adversario = trim($_POST['adversario'] ?? '');
        $data_partida = trim($_POST['data_partida'] ?? '');
        $partidaFutura = $data_partida > date('Y-m-d');
        $local = trim($_POST['local'] ?? '');
        $competicao = trim($_POST['competicao'] ?? '');
        $gols_time = $partidaFutura ? 0 : max(0, (int)($_POST['gols_time'] ?? 0));
        $gols_adversario = $partidaFutura ? 0 : max(0, (int)($_POST['gols_adversario'] ?? 0));

        $stmtPartida = $pdo->prepare("SELECT id FROM partidas WHERE id = ? AND usuario_id = ?");
        $stmtPartida->execute([$partida_id, $_SESSION['user_id']]);

        if ($partida_id > 0 && $stmtPartida->fetch() && $adversario !== '' && $data_partida !== '') {
            $stmtAtletas = $pdo->prepare("SELECT a.id FROM atletas a WHERE a.usuario_id = ? AND NOT EXISTS (SELECT 1 FROM lesoes l WHERE l.atleta_id = a.id AND l.status = 'Em Tratamento') ORDER BY a.nome ASC");
            $stmtAtletas->execute([$_SESSION['user_id']]);
            $suspensoesAtivas = obterSuspensoesAtivas($pdo, (int)$_SESSION['user_id']);
            $estatisticasAtletas = [];
            $totalGolsAtletas = 0;

            foreach ($stmtAtletas->fetchAll() as $atleta) {
                $atleta_id = (int)$atleta['id'];
                if (!empty($suspensoesAtivas[$atleta_id]['suspenso'])) continue;
                $gols = $partidaFutura ? 0 : max(0, (int)($_POST['gols_' . $atleta_id] ?? 0));
                $estatisticasAtletas[$atleta_id] = [
                    'gols' => $gols,
                    'assistencias' => $partidaFutura ? 0 : max(0, (int)($_POST['assistencias_' . $atleta_id] ?? 0)),
                    'cartoes_amarelos' => $partidaFutura ? 0 : min(2, max(0, (int)($_POST['cartoes_amarelos_' . $atleta_id] ?? 0))),
                    'cartoes_vermelhos' => $partidaFutura ? 0 : min(1, max(0, (int)($_POST['cartoes_vermelhos_' . $atleta_id] ?? 0))),
                ];
                $totalGolsAtletas += $gols;
            }

            if ($totalGolsAtletas > $gols_time) {
                $_SESSION['match_error'] = "Os gols dos atletas ({$totalGolsAtletas}) não podem ser maiores que os gols do time no placar ({$gols_time}).";
                header('Location: index.php?edit_match=' . $partida_id);
                exit;
            }

            $pdo->beginTransaction();
            try {
                $stmtUpdate = $pdo->prepare("UPDATE partidas SET adversario = ?, data_partida = ?, local = ?, competicao = ?, resultado = ? WHERE id = ? AND usuario_id = ?");
                $stmtUpdate->execute([$adversario, $data_partida, $local, $competicao, $gols_time . ' x ' . $gols_adversario, $partida_id, $_SESSION['user_id']]);

                if ($partidaFutura) {
                    $stmtDeleteAllStats = $pdo->prepare("DELETE FROM estatisticas_partidas WHERE partida_id = ?");
                    $stmtDeleteAllStats->execute([$partida_id]);
                }

                $stmtDeleteStats = $pdo->prepare("DELETE FROM estatisticas_partidas WHERE partida_id = ? AND atleta_id = ?");
                $stmtInsertStats = $pdo->prepare("INSERT INTO estatisticas_partidas (partida_id, atleta_id, gols, assistencias, cartoes_amarelos, cartoes_vermelhos) VALUES (?, ?, ?, ?, ?, ?)");

                foreach ($estatisticasAtletas as $atleta_id => $estatisticas) {
                    $stmtDeleteStats->execute([$partida_id, $atleta_id]);
                    if (array_sum($estatisticas) > 0) {
                        $stmtInsertStats->execute([$partida_id, $atleta_id, $estatisticas['gols'], $estatisticas['assistencias'], $estatisticas['cartoes_amarelos'], $estatisticas['cartoes_vermelhos']]);
                    }
                }
                $pdo->commit();
            } catch (Throwable $e) {
                $pdo->rollBack();
                throw $e;
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
        $stmt = $pdo->prepare("SELECT a.*, EXISTS (
                SELECT 1 FROM lesoes l
                WHERE l.atleta_id = a.id AND l.status = 'Em Tratamento'
            ) AS lesionado
            FROM atletas a
            WHERE a.usuario_id = ?
            ORDER BY a.nome ASC");
        $stmt->execute([$_SESSION['user_id']]);
        $atletas = $stmt->fetchAll();
        $suspensoesAtivas = obterSuspensoesAtivas($pdo, (int)$_SESSION['user_id']);
        $atletasDisponiveis = array_values(array_filter($atletas, static fn($atleta) => !(bool)$atleta['lesionado'] && empty($suspensoesAtivas[(int)$atleta['id']]['suspenso'])));
        $atletasSuspensos = array_filter($suspensoesAtivas, static fn($status) => $status['suspenso']);
        $atletasComAmarelos = array_filter($suspensoesAtivas, static fn($status) => $status['amarelos'] > 0 && !$status['suspenso']);

        // Busca lista de partidas cadastradas
        $stmtPartidas = $pdo->prepare("SELECT * FROM partidas WHERE usuario_id = ? ORDER BY data_partida DESC, id DESC");
        $stmtPartidas->execute([$_SESSION['user_id']]);
        $partidas = $stmtPartidas->fetchAll();

        $editMatch = null;
        $editStats = [];
        $editMatchId = (int)($_GET['edit_match'] ?? 0);
        if ($editMatchId > 0) {
            $stmtEditMatch = $pdo->prepare("SELECT * FROM partidas WHERE id = ? AND usuario_id = ?");
            $stmtEditMatch->execute([$editMatchId, $_SESSION['user_id']]);
            $editMatch = $stmtEditMatch->fetch();

            if ($editMatch) {
                $stmtEditStats = $pdo->prepare("SELECT atleta_id, gols, assistencias, cartoes_amarelos, cartoes_vermelhos FROM estatisticas_partidas WHERE partida_id = ?");
                $stmtEditStats->execute([$editMatchId]);
                foreach ($stmtEditStats->fetchAll() as $estatistica) {
                    $editStats[(int)$estatistica['atleta_id']] = $estatistica;
                }
            }
        }

        $placarEdicao = [0, 0];
        if ($editMatch && preg_match('/^(\d+)\s*x\s*(\d+)$/i', (string)$editMatch['resultado'], $resultadoPartida)) {
            $placarEdicao = [(int)$resultadoPartida[1], (int)$resultadoPartida[2]];
        }
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
                <a href="estatisticas.php" class="btn-primary" style="width: auto; padding: 0.6rem 1.2rem; text-decoration: none; display: inline-block;">ESTATÍSTICAS DO ELENCO</a>
                <button class="btn-primary" style="width: auto; padding: 0.6rem 1.2rem;" onclick="openModal('modalMatch')">+ CADASTRAR PARTIDA</button>
                <button class="btn-primary" style="width: auto; padding: 0.6rem 1.2rem;" onclick="openModal('modalAthlete')">+ ADICIONAR ATLETA</button>
            </div>
        </div>

        <?php if (!empty($atletasSuspensos)): ?>
            <div class="suspension-alert" role="alert">
                <strong>Suspensos na próxima rodada:</strong>
                <?php foreach ($atletasSuspensos as $suspenso): ?>
                    <span><?= htmlspecialchars($suspenso['nome']) ?> (<?= htmlspecialchars($suspenso['motivo']) ?>)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($atletasComAmarelos)): ?>
            <div class="discipline-alert">
                <strong>Amarelos acumulados:</strong>
                <?php foreach ($atletasComAmarelos as $atletaComAmarelos): ?>
                    <span><?= htmlspecialchars($atletaComAmarelos['nome']) ?> (<?= (int)$atletaComAmarelos['amarelos'] ?>/3)</span>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="cards-grid">
            <?php foreach ($atletas as $atleta): ?>
                <div class="atleta-card-link-wrap">
                    <a href="atleta.php?id=<?= $atleta['id'] ?>" class="atleta-card-link" style="text-decoration: none; color: inherit;">
                        <div class="atleta-card">
                            <div class="atleta-foto-container">
                                <img src="<?= htmlspecialchars($atleta['foto_url'] ?: 'img/default.jpg') ?>" alt="Foto">
                                <span class="atleta-numero">
                                    <?= $atleta['numero'] ?>
                                    <?php if ($atleta['lesionado']): ?>
                                        <span class="atleta-lesao-icon" title="Atleta lesionado" aria-label="Atleta lesionado">🚑</span>
                                    <?php endif; ?>
                                </span>
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
                    <a class="match-card match-card-edit" href="index.php?edit_match=<?= (int)$partida['id'] ?>" aria-label="Editar partida contra <?= htmlspecialchars($partida['adversario']) ?>">
                        <div class="match-header">
                            <span class="match-date"><?= date('d/m/Y', strtotime($partida['data_partida'])) ?></span>
                            <span class="match-result"><?= $partida['data_partida'] > date('Y-m-d') ? 'EM BREVE' : htmlspecialchars($partida['resultado'] ?: 'Resultado não informado') ?></span>
                        </div>
                        <div class="match-body">
                            <strong><?= htmlspecialchars($partida['adversario']) ?></strong>
                            <span><?= htmlspecialchars($partida['competicao'] ?: 'JIFC') ?></span>
                            <span><?= htmlspecialchars($partida['local'] ?: 'Local não informado') ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- MODAL CADASTRAR / EDITAR PARTIDA -->
    <div class="modal" id="modalMatch">
        <div class="modal-content modal-large">
            <span class="close-btn" onclick="closeModal('modalMatch')">&times;</span>
            <h2 style="margin-bottom: 1.5rem;"><?= $editMatch ? 'EDITAR PARTIDA' : 'CADASTRAR PARTIDA' ?></h2>
            <form method="POST" id="matchForm">
                <input type="hidden" name="action" value="<?= $editMatch ? 'edit_match' : 'add_match' ?>">
                <?php if ($editMatch): ?>
                    <input type="hidden" name="partida_id" value="<?= (int)$editMatch['id'] ?>">
                <?php endif; ?>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>ADVERSÁRIO</label>
                        <input type="text" name="adversario" class="form-control" value="<?= htmlspecialchars($editMatch['adversario'] ?? '') ?>" required>
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>DATA DA PARTIDA</label>
                        <input type="date" name="data_partida" id="dataPartida" class="form-control" value="<?= htmlspecialchars($editMatch['data_partida'] ?? '') ?>" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group" style="flex: 1;">
                        <label>LOCAL</label>
                        <input type="text" name="local" class="form-control" placeholder="Ginásio" value="<?= htmlspecialchars($editMatch['local'] ?? '') ?>">
                    </div>
                    <div class="form-group" style="flex: 1;">
                        <label>COMPETIÇÃO</label>
                        <input type="text" name="competicao" class="form-control" value="<?= htmlspecialchars($editMatch['competicao'] ?? 'Interclasse') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>PLACAR DO JOGO</label>
                    <div style="display: flex; gap: 0.75rem; align-items: center;">
                        <input type="number" name="gols_time" id="golsTime" class="form-control" value="<?= $placarEdicao[0] ?>" min="0" style="width: 90px;">
                        <span style="color: #a3e635; font-weight: 900;">x</span>
                        <input type="number" name="gols_adversario" class="form-control" value="<?= $placarEdicao[1] ?>" min="0" style="width: 90px;">
                    </div>
                </div>
                <p class="match-future-notice" id="matchFutureNotice" hidden>Partida futura: placar e estatísticas serão liberados após a data do jogo.</p>
                <p class="match-goals-error" id="matchGoalsError" role="alert"<?= $matchError ? '' : ' hidden' ?>><?= htmlspecialchars($matchError) ?></p>

                <div class="match-table-wrap">
                    <table class="match-table">
                        <thead>
                            <tr>
                                <th>ATLETA</th>
                                <th>GOLS</th>
                                <th>ASSIST.</th>
                                <th>CA</th>
                                <th>CV</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($atletasDisponiveis as $atleta): ?>
                                <tr>
                                    <td class="match-player">
                                        <span><?= htmlspecialchars($atleta['nome']) ?></span>
                                        <small><?= htmlspecialchars($atleta['posicao']) ?></small>
                                    </td>
                                    <td><input type="number" name="gols_<?= $atleta['id'] ?>" value="<?= (int)($editStats[$atleta['id']]['gols'] ?? 0) ?>" min="0" class="form-control small-input match-player-goals"></td>
                                    <td><input type="number" name="assistencias_<?= $atleta['id'] ?>" value="<?= (int)($editStats[$atleta['id']]['assistencias'] ?? 0) ?>" min="0" class="form-control small-input"></td>
                                    <td>
                                        <select name="cartoes_amarelos_<?= $atleta['id'] ?>" class="form-control small-input">
                                            <?php for ($cartoes = 0; $cartoes <= 2; $cartoes++): ?>
                                                <option value="<?= $cartoes ?>"<?= (int)($editStats[$atleta['id']]['cartoes_amarelos'] ?? 0) === $cartoes ? ' selected' : '' ?>><?= $cartoes ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <select name="cartoes_vermelhos_<?= $atleta['id'] ?>" class="form-control small-input">
                                            <?php for ($cartoes = 0; $cartoes <= 1; $cartoes++): ?>
                                                <option value="<?= $cartoes ?>"<?= (int)($editStats[$atleta['id']]['cartoes_vermelhos'] ?? 0) === $cartoes ? ' selected' : '' ?>><?= $cartoes ?></option>
                                            <?php endfor; ?>
                                        </select>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <button type="submit" class="btn-primary" style="width: auto; padding: 0.8rem 1.5rem; margin-top: 1rem;"><?= $editMatch ? 'SALVAR ALTERAÇÕES' : 'SALVAR PARTIDA' ?></button>
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

    const matchForm = document.getElementById('matchForm');
    if (matchForm) {
        const matchDate = document.getElementById('dataPartida');
        const teamGoals = document.getElementById('golsTime');
        const goalInputs = matchForm.querySelectorAll('.match-player-goals');
        const error = document.getElementById('matchGoalsError');
        const futureNotice = document.getElementById('matchFutureNotice');
        const submitButton = matchForm.querySelector('[type="submit"]');
        let serverError = error.textContent.trim();

        function validatePlayerGoals() {
            const goalValues = Array.from(goalInputs)
                .map((input) => Math.max(0, Number(input.value) || 0));
            const scoredByPlayers = goalValues.reduce((total, goals) => total + goals, 0);
            const scoredByTeam = Math.max(0, Number(teamGoals.value) || 0);
            const isInvalid = scoredByPlayers > scoredByTeam;

            goalInputs.forEach((input, index) => {
                input.max = Math.max(0, scoredByTeam - (scoredByPlayers - goalValues[index]));
            });

            error.textContent = isInvalid
                ? `Os gols dos atletas (${scoredByPlayers}) não podem ultrapassar os gols do time no placar (${scoredByTeam}).`
                : serverError;
            error.hidden = !(isInvalid || serverError);
            submitButton.disabled = isInvalid;
            return !isInvalid;
        }

        function updateGoals() {
            serverError = '';
            validatePlayerGoals();
        }

        function updateFutureMatchFields() {
            const today = new Date();
            const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
                .toISOString()
                .slice(0, 10);
            const isFuture = Boolean(matchDate.value && matchDate.value > localToday);
            matchForm.querySelectorAll('[name="gols_time"], [name="gols_adversario"], [name^="gols_"], [name^="assistencias_"], [name^="cartoes_amarelos_"], [name^="cartoes_vermelhos_"]')
                .forEach((field) => field.disabled = isFuture);
            futureNotice.hidden = !isFuture;
            validatePlayerGoals();
        }

        teamGoals.addEventListener('input', updateGoals);
        goalInputs.forEach((input) => input.addEventListener('input', updateGoals));
        matchDate.addEventListener('change', updateFutureMatchFields);
        matchForm.addEventListener('submit', (event) => {
            if (!validatePlayerGoals()) event.preventDefault();
        });
        validatePlayerGoals();

        // Usa a data local do dispositivo do usuário, sem impedir uma alteração manual.
        if (!matchDate.value) {
            const today = new Date();
            const localToday = new Date(today.getTime() - today.getTimezoneOffset() * 60000)
                .toISOString()
                .slice(0, 10);
            matchDate.value = localToday;
        }
        updateFutureMatchFields();
    }

    <?php if ($matchError || $editMatch): ?>
    openModal('modalMatch');
    <?php endif; ?>

    // Fecha o modal se clicar fora dele
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.style.display = "none";
        }
    }
</script>

</body>
</html>
