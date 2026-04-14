<?php
// admin/ajax_get_alunos.php
session_start();
require_once '../config/database.php';
require_once '../includes/auth.php';

if (!isLoggedIn() || !isAdmin()) {
    exit('Acesso negado');
}

$turma_id = (int)$_GET['turma_id'];

$database = new Database();
$db = $database->getConnection();

// Buscar alunos já na turma
$alunos_na_turma = [];
$query = "SELECT aluno_id FROM enturmacoes WHERE turma_id = $turma_id";
$result = $db->query($query);
while ($row = $result->fetch_assoc()) {
    $alunos_na_turma[] = $row['aluno_id'];
}

// Buscar todos alunos ativos
$query = "SELECT a.id, u.nome, a.numero_matricula
          FROM alunos a
          INNER JOIN usuarios u ON a.usuario_id = u.id
          WHERE u.ativo = 1
          ORDER BY u.nome ASC";
$alunos = $db->query($query);
?>

<div class="select-all mb-3">
    <div class="form-check">
        <input class="form-check-input" type="checkbox" id="selectAllAjax">
        <label class="form-check-label" for="selectAllAjax">
            <strong>Selecionar todos os alunos</strong>
        </label>
    </div>
</div>

<div class="row">
    <?php while ($aluno = $alunos->fetch_assoc()): 
        $ja_enturmado = in_array($aluno['id'], $alunos_na_turma);
    ?>
    <div class="col-md-6 mb-2">
        <div class="form-check">
            <input class="form-check-input aluno-checkbox-ajax" 
                   type="checkbox" 
                   name="alunos[]" 
                   value="<?php echo $aluno['id']; ?>"
                   id="aluno_<?php echo $aluno['id']; ?>"
                   <?php echo $ja_enturmado ? 'disabled' : ''; ?>>
            <label class="form-check-label <?php echo $ja_enturmado ? 'text-muted' : ''; ?>" 
                   for="aluno_<?php echo $aluno['id']; ?>">
                <?php echo htmlspecialchars($aluno['nome']); ?>
                <small class="text-muted">
                    (<?php echo htmlspecialchars($aluno['numero_matricula']); ?>)
                </small>
                <?php if ($ja_enturmado): ?>
                <span class="badge bg-secondary ms-2">Já enturmado</span>
                <?php endif; ?>
            </label>
        </div>
    </div>
    <?php endwhile; ?>
</div>

<script>
$('#selectAllAjax').on('change', function() {
    $('.aluno-checkbox-ajax:not(:disabled)').prop('checked', $(this).prop('checked'));
});
</script>