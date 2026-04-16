<?php
// admin/visualizar_relatorio.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

// Verificar se é admin
if (!isLoggedIn() || !isAdmin()) {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$tipo = $_GET['tipo'] ?? '';
$ano_letivo = (int)($_GET['ano'] ?? date('Y'));
$trimestre = (int)($_GET['trimestre'] ?? 0);
$turma_id = (int)($_GET['turma_id'] ?? 0);
$disciplina_id = (int)($_GET['disciplina_id'] ?? 0);
$aluno_id = (int)($_GET['aluno_id'] ?? 0);
$formato = $_GET['formato'] ?? 'html';

// Registrar geração de relatório
$ip = $_SERVER['REMOTE_ADDR'];
$params = json_encode($_GET);
$db->query("INSERT INTO logs_auditoria (usuario_id, acao, tabela, dados_novos, ip) 
            VALUES ({$_SESSION['user_id']}, 'GERAR_RELATORIO', 'relatorios', '$params', '$ip')");

// Carregar dados baseado no tipo
$dados = [];
$titulo = '';
$erro = '';

switch ($tipo) {
    case 'boletim':
        if (!$aluno_id) {
            $erro = "ID do aluno não fornecido.";
            break;
        }
        $titulo = 'Boletim Individual';
        // Buscar dados do aluno
        $query = "SELECT u.nome, a.numero_matricula 
                  FROM alunos a 
                  INNER JOIN usuarios u ON a.usuario_id = u.id 
                  WHERE a.id = $aluno_id";
        $resultado = $db->query($query);
        if (!$resultado) {
            $erro = "Erro na query: " . $db->error;
            break;
        }
        $aluno = $resultado->fetch_assoc();
        
        // Buscar notas do aluno (nova estrutura: nota_trimestre)
        $query = "SELECT d.nome as disciplina, n.nota_trimestre, n.trimestre, n.estado 
                  FROM notas n
                  INNER JOIN disciplinas d ON n.disciplina_id = d.id
                  WHERE n.aluno_id = $aluno_id AND n.ano_letivo = $ano_letivo";
        if ($trimestre) {
            $query .= " AND n.trimestre = $trimestre";
        }
        $query .= " ORDER BY n.trimestre, d.nome";
        $notas = $db->query($query);
        if (!$notas && $db->error) {
            $erro = "Erro ao buscar notas: " . $db->error;
        }
        break;
        
    case 'pauta':
        if (!$turma_id) {
            $erro = "ID da turma não fornecido.";
            break;
        }
        $titulo = 'Pauta de Turma';
        // Buscar dados da turma
        $query = "SELECT nome, ano_letivo, curso FROM turmas WHERE id = $turma_id";
        $resultado = $db->query($query);
        if (!$resultado) {
            $erro = "Erro na query: " . $db->error;
            break;
        }
        $turma = $resultado->fetch_assoc();
        
        // Buscar alunos da turma com notas (estrutura simplificada)
        // Para cada aluno e disciplina, buscar nota_trimestre
        $query = "SELECT u.nome as aluno_nome, a.numero_matricula, d.nome as disciplina, 
                         n.nota_trimestre, n.trimestre, n.estado
                  FROM enturmacoes e
                  INNER JOIN alunos a ON e.aluno_id = a.id
                  INNER JOIN usuarios u ON a.usuario_id = u.id
                  CROSS JOIN turma_disciplina td
                  INNER JOIN disciplinas d ON td.disciplina_id = d.id
                  LEFT JOIN notas n ON n.aluno_id = a.id AND n.disciplina_id = d.id 
                      AND n.ano_letivo = $ano_letivo
                  WHERE e.turma_id = $turma_id AND td.turma_id = $turma_id";
        if ($trimestre) {
            $query .= " AND (n.trimestre = $trimestre OR n.trimestre IS NULL)";
        }
        $query .= " ORDER BY u.nome, d.nome";
        $pauta = $db->query($query);
        if (!$pauta && $db->error) {
            $erro = "Erro ao buscar pauta: " . $db->error;
        }
        break;
        
    case 'disciplina':
        if (!$disciplina_id) {
            $erro = "ID da disciplina não fornecido.";
            break;
        }
        $titulo = 'Notas por Disciplina';
        // Buscar dados da disciplina
        $query = "SELECT nome, codigo FROM disciplinas WHERE id = $disciplina_id";
        $resultado = $db->query($query);
        if (!$resultado) {
            $erro = "Erro na query: " . $db->error;
            break;
        }
        $disciplina = $resultado->fetch_assoc();
        
        // Buscar notas da disciplina (nova estrutura)
        $query = "SELECT u.nome as aluno_nome, a.numero_matricula, t.nome as turma, 
                         n.nota_trimestre, n.trimestre, n.estado
                  FROM notas n
                  INNER JOIN alunos a ON n.aluno_id = a.id
                  INNER JOIN usuarios u ON a.usuario_id = u.id
                  INNER JOIN enturmacoes e ON a.id = e.aluno_id
                  INNER JOIN turmas t ON e.turma_id = t.id
                  WHERE n.disciplina_id = $disciplina_id AND n.ano_letivo = $ano_letivo";
        if ($trimestre) {
            $query .= " AND n.trimestre = $trimestre";
        }
        if ($turma_id) {
            $query .= " AND t.id = $turma_id";
        }
        $query .= " ORDER BY t.nome, u.nome";
        $notas = $db->query($query);
        if (!$notas && $db->error) {
            $erro = "Erro ao buscar notas: " . $db->error;
        }
        break;
        
    case 'aproveitamento':
        $titulo = 'Aproveitamento Geral';
        // Estatísticas por turma/disciplina usando nota_trimestre
        $query = "SELECT t.nome as turma, d.nome as disciplina,
                  COUNT(n.id) as total_notas,
                  SUM(CASE WHEN n.estado = 'Aprovado' THEN 1 ELSE 0 END) as aprovados,
                  SUM(CASE WHEN n.estado = 'Reprovado' THEN 1 ELSE 0 END) as reprovados,
                  AVG(n.nota_trimestre) as media
                  FROM turmas t
                  CROSS JOIN turma_disciplina td ON t.id = td.turma_id
                  INNER JOIN disciplinas d ON td.disciplina_id = d.id
                  LEFT JOIN notas n ON n.disciplina_id = d.id AND n.ano_letivo = t.ano_letivo
                  WHERE t.ano_letivo = $ano_letivo";
        if ($turma_id) {
            $query .= " AND t.id = $turma_id";
        }
        if ($disciplina_id) {
            $query .= " AND d.id = $disciplina_id";
        }
        $query .= " GROUP BY t.id, d.id ORDER BY t.nome, d.nome";
        $estatisticas = $db->query($query);
        if (!$estatisticas && $db->error) {
            $erro = "Erro ao buscar estatísticas: " . $db->error;
        }
        break;
        
    default:
        die('Tipo de relatório inválido');
}

// Se formato for PDF ou Excel, redirecionar para geradores específicos
if ($formato === 'pdf') {
    // Implementar geração de PDF
    header('Location: gerar_pdf.php?' . http_build_query($_GET));
    exit();
} elseif ($formato === 'excel') {
    // Implementar geração de Excel
    header('Location: gerar_excel.php?' . http_build_query($_GET));
    exit();
}

// Caso contrário, mostrar em HTML
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $titulo; ?> - IPOK</title>
    <link rel="shortcut icon" href="../assets/img/logo.png" type="image/x-icon">
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #1e3c72;
            --secondary-blue: #2a5298;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f4f7fc;
            padding: 20px;
        }
        
        .relatorio-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 30px;
        }
        
        .relatorio-header {
            border-bottom: 3px solid var(--primary-blue);
            padding-bottom: 20px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .relatorio-header h1 {
            color: var(--primary-blue);
            font-size: 2rem;
            margin: 0;
        }
        
        .relatorio-header .instituto {
            color: #6c757d;
            font-size: 1rem;
        }
        
        .info-section {
            background: #e6f0fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .info-item {
            flex: 1;
            min-width: 200px;
        }
        
        .info-label {
            font-size: 0.85rem;
            color: #6c757d;
            text-transform: uppercase;
        }
        
        .info-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .table-relatorio {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .table-relatorio th {
            background: var(--primary-blue);
            color: white;
            padding: 12px;
            font-weight: 500;
        }
        
        .table-relatorio td {
            padding: 10px 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .table-relatorio tr:hover {
            background: #f8f9fa;
        }
        
        .nota-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-weight: 600;
            min-width: 50px;
            text-align: center;
        }
        
        .nota-alta { background: #d4edda; color: #155724; }
        .nota-media { background: #fff3cd; color: #856404; }
        .nota-baixa { background: #f8d7da; color: #721c24; }
        
        .estado-aprovado {
            color: #28a745;
            font-weight: 600;
        }
        
        .estado-reprovado {
            color: #dc3545;
            font-weight: 600;
        }
        
        .footer-actions {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            display: flex;
            gap: 10px;
            justify-content: flex-end;
        }
        
        .btn-pdf {
            background: #dc3545;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        
        .btn-excel {
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        
        .btn-voltar {
            background: #6c757d;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-decoration: none;
        }
        
        @media print {
            .no-print, .footer-actions {
                display: none;
            }
            body { background: white; }
            .relatorio-container { box-shadow: none; padding: 0; }
        }
    </style>
</head>
<body>
    <div class="relatorio-container">
        <?php if ($erro): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <strong>Erro:</strong> <?php echo htmlspecialchars($erro); ?>
        </div>
        <a href="relatorios.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
        <?php else: ?>
        <div class="relatorio-header no-print">
            <div>
                <h1><?php echo $titulo; ?></h1>
                <div class="instituto">Instituto Politécnico do Kituma (IPOK)</div>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-primary">
                    <i class="fas fa-print me-2"></i>Imprimir
                </button>
            </div>
        </div>
        
        <!-- Informações gerais -->
        <div class="info-section">
            <div class="info-item">
                <div class="info-label">Ano Letivo</div>
                <div class="info-value"><?php echo $ano_letivo; ?></div>
            </div>
            <?php if ($trimestre): ?>
            <div class="info-item">
                <div class="info-label">Trimestre</div>
                <div class="info-value"><?php echo $trimestre; ?>º Trimestre</div>
            </div>
            <?php endif; ?>
            
            <?php if ($tipo === 'boletim' && isset($aluno)): ?>
            <div class="info-item">
                <div class="info-label">Aluno</div>
                <div class="info-value"><?php echo htmlspecialchars($aluno['nome']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Matrícula</div>
                <div class="info-value"><?php echo htmlspecialchars($aluno['numero_matricula']); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($tipo === 'pauta' && isset($turma)): ?>
            <div class="info-item">
                <div class="info-label">Turma</div>
                <div class="info-value"><?php echo htmlspecialchars($turma['nome']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Curso</div>
                <div class="info-value"><?php echo htmlspecialchars($turma['curso'] ?? '---'); ?></div>
            </div>
            <?php endif; ?>
            
            <?php if ($tipo === 'disciplina' && isset($disciplina)): ?>
            <div class="info-item">
                <div class="info-label">Disciplina</div>
                <div class="info-value"><?php echo htmlspecialchars($disciplina['nome']); ?></div>
            </div>
            <div class="info-item">
                <div class="info-label">Código</div>
                <div class="info-value"><?php echo htmlspecialchars($disciplina['codigo'] ?? '---'); ?></div>
            </div>
            <?php endif; ?>
            
            <div class="info-item">
                <div class="info-label">Data de Emissão</div>
                <div class="info-value"><?php echo date('d/m/Y H:i'); ?></div>
            </div>
        </div>
        
        <!-- Conteúdo do relatório -->
        <?php if ($tipo === 'boletim'): ?>
            <h4 class="mb-3">Notas do Aluno</h4>
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Disciplina</th>
                        <th>Trimestre</th>
                        <th>Nota</th>
                        <th>Estado</th>
                     </thead>
                <tbody>
                    <?php while ($nota = $notas->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($nota['disciplina']); ?></td>
                        <td><?php echo $nota['trimestre']; ?>º Trimestre</td>
                        <td>
                            <?php if ($nota['nota_trimestre'] !== null): ?>
                                <span class="nota-badge 
                                    <?php 
                                    if ($nota['nota_trimestre'] >= 14) echo 'nota-alta';
                                    elseif ($nota['nota_trimestre'] >= 10) echo 'nota-media';
                                    else echo 'nota-baixa';
                                    ?>">
                                    <?php echo number_format($nota['nota_trimestre'], 1); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                         </td>
                        <td>
                            <?php if ($nota['estado']): ?>
                                <span class="<?php echo $nota['estado'] === 'Aprovado' ? 'estado-aprovado' : 'estado-reprovado'; ?>">
                                    <?php echo $nota['estado']; ?>
                                </span>
                            <?php else: ?>
                                <span class="text-muted">Incompleto</span>
                            <?php endif; ?>
                         </td>
                     </tr>
                    <?php endwhile; ?>
                </tbody>
             </table>
            
        <?php elseif ($tipo === 'pauta'): ?>
            <h4 class="mb-3">Pauta da Turma <?php echo htmlspecialchars($turma['nome']); ?></h4>
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Matrícula</th>
                        <th>Disciplina</th>
                        <th>Nota</th>
                        <th>Estado</th>
                     </thead>
                <tbody>
                    <?php 
                    $aluno_atual = '';
                    while ($linha = $pauta->fetch_assoc()): 
                        if ($aluno_atual != $linha['aluno_nome']):
                            $aluno_atual = $linha['aluno_nome'];
                    ?>
                    <tr style="background: #f8f9fa;">
                        <td colspan="5" class="fw-bold">
                            <?php echo htmlspecialchars($linha['aluno_nome']); ?> 
                            (<?php echo $linha['numero_matricula']; ?>)
                         </td>
                     </tr>
                    <?php endif; ?>
                    <tr>
                         <td></td>
                         <td></td>
                         <td><?php echo htmlspecialchars($linha['disciplina']); ?></td>
                         <td>
                            <?php if ($linha['nota_trimestre'] !== null): ?>
                                <span class="nota-badge 
                                    <?php 
                                    if ($linha['nota_trimestre'] >= 14) echo 'nota-alta';
                                    elseif ($linha['nota_trimestre'] >= 10) echo 'nota-media';
                                    else echo 'nota-baixa';
                                    ?>">
                                    <?php echo number_format($linha['nota_trimestre'], 1); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                         </td>
                         <td>
                            <?php if ($linha['estado']): ?>
                                <span class="<?php echo $linha['estado'] === 'Aprovado' ? 'estado-aprovado' : 'estado-reprovado'; ?>">
                                    <?php echo $linha['estado']; ?>
                                </span>
                            <?php endif; ?>
                         </td>
                     </tr>
                    <?php endwhile; ?>
                </tbody>
             </table>
            
        <?php elseif ($tipo === 'disciplina'): ?>
            <h4 class="mb-3">Notas de <?php echo htmlspecialchars($disciplina['nome']); ?></h4>
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Aluno</th>
                        <th>Matrícula</th>
                        <th>Turma</th>
                        <th>Trimestre</th>
                        <th>Nota</th>
                        <th>Estado</th>
                     </thead>
                <tbody>
                    <?php while ($nota = $notas->fetch_assoc()): ?>
                    <tr>
                         <td><?php echo htmlspecialchars($nota['aluno_nome']); ?></td>
                         <td><?php echo htmlspecialchars($nota['numero_matricula']); ?></td>
                         <td><?php echo htmlspecialchars($nota['turma']); ?></td>
                         <td><?php echo $nota['trimestre']; ?>º Trimestre</td>
                         <td>
                            <?php if ($nota['nota_trimestre'] !== null): ?>
                                <span class="nota-badge 
                                    <?php 
                                    if ($nota['nota_trimestre'] >= 14) echo 'nota-alta';
                                    elseif ($nota['nota_trimestre'] >= 10) echo 'nota-media';
                                    else echo 'nota-baixa';
                                    ?>">
                                    <?php echo number_format($nota['nota_trimestre'], 1); ?>
                                </span>
                            <?php else: ?>
                                -
                            <?php endif; ?>
                         </td>
                         <td>
                            <?php if ($nota['estado']): ?>
                                <span class="<?php echo $nota['estado'] === 'Aprovado' ? 'estado-aprovado' : 'estado-reprovado'; ?>">
                                    <?php echo $nota['estado']; ?>
                                </span>
                            <?php endif; ?>
                         </td>
                     </tr>
                    <?php endwhile; ?>
                </tbody>
             </table>
            
        <?php elseif ($tipo === 'aproveitamento'): ?>
            <h4 class="mb-3">Aproveitamento por Turma/Disciplina</h4>
            <table class="table-relatorio">
                <thead>
                    <tr>
                        <th>Turma</th>
                        <th>Disciplina</th>
                        <th>Total Notas</th>
                        <th>Aprovados</th>
                        <th>Reprovados</th>
                        <th>Média</th>
                        <th>Aproveitamento</th>
                     </thead>
                <tbody>
                    <?php while ($est = $estatisticas->fetch_assoc()): 
                        $total = $est['aprovados'] + $est['reprovados'];
                        $taxa = $total > 0 ? round(($est['aprovados'] / $total) * 100, 1) : 0;
                    ?>
                    <tr>
                         <td><?php echo htmlspecialchars($est['turma']); ?></td>
                         <td><?php echo htmlspecialchars($est['disciplina']); ?></td>
                         <td class="text-center"><?php echo $est['total_notas']; ?></td>
                         <td class="text-success fw-bold"><?php echo $est['aprovados']; ?></td>
                         <td class="text-danger fw-bold"><?php echo $est['reprovados']; ?></td>
                         <td class="text-center"><?php echo round($est['media'] ?? 0, 1); ?></td>
                         <td>
                            <div class="progress" style="height: 20px;">
                                <div class="progress-bar bg-success" 
                                     style="width: <?php echo $taxa; ?>%">
                                    <?php echo $taxa; ?>%
                                </div>
                            </div>
                         </td>
                     </tr>
                    <?php endwhile; ?>
                </tbody>
             </table>
        <?php endif; ?>
        
        <!-- Rodapé com ações -->
        <div class="footer-actions no-print">
            <a href="relatorios.php" class="btn-voltar">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['formato' => 'pdf'])); ?>" class="btn-pdf">
                <i class="fas fa-file-pdf me-2"></i>Exportar PDF
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['formato' => 'excel'])); ?>" class="btn-excel">
                <i class="fas fa-file-excel me-2"></i>Exportar Excel
            </a>
        </div>
        
        <div class="text-center text-muted mt-3 no-print" style="font-size: 0.85rem;">
            Documento gerado em <?php echo date('d/m/Y H:i:s'); ?> por <?php echo htmlspecialchars($_SESSION['user_nome']); ?>
        </div>
        <?php endif; ?>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>