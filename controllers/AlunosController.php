<?php
require_once __DIR__ . '/Controller.php';

class AlunosController extends Controller {
    private $aluno;
    private $turma;
    private $curso;
    private $evento;
    private $configuracao;
    
    public function __construct() {
        parent::__construct();
        $this->aluno = new Aluno($this->db);
        $this->turma = new Turma($this->db);
        $this->curso = new Curso($this->db);
        $this->evento = new Evento($this->db);
        $this->configuracao = new Configuracao($this->db);
    }
    
    /**
     * Lista de alunos
     */
    public function index() {
        $this->requirePermission();
        
        // Process POST requests
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
            return; // Redirect will happen in handlePost
        }
        
        // Get filters
        $filtro_curso = $_GET['filtro_curso'] ?? '';
        $filtro_turma = $_GET['filtro_turma'] ?? '';
        $filtro_nome = $_GET['filtro_nome'] ?? '';
        
        // Get data
        $alunos = $this->aluno->getAll();
        $turmas = $this->turma->getAll();
        $cursos = $this->curso->getAll();
        $ano_corrente = $this->configuracao->getAnoCorrente();
        $turmas_ano_corrente_lista = $this->turma->getTurmasPorAnoCorrente($ano_corrente);
        
        // Nivel2 só vê eventos que ele mesmo registrou
        $user_id = $_SESSION['user_id'] ?? null;
        $registrado_por = ($this->user->isNivel2()) ? $user_id : null;
        
        // Process alunos data
        foreach ($alunos as &$a) {
            $turmas_aluno = $this->aluno->getTurmasAluno($a['id']);
            // Buscar primeira turma do ano corrente
            $turma_ano_corrente_aluno = null;
            foreach ($turmas_aluno as $ta) {
                if ($ta['ano_civil'] == $ano_corrente) {
                    $turma_ano_corrente_aluno = $ta;
                    break;
                }
            }
            // Se não encontrou do ano corrente, pega a primeira
            if (!$turma_ano_corrente_aluno && !empty($turmas_aluno)) {
                $turma_ano_corrente_aluno = $turmas_aluno[0];
            }
            
            if ($turma_ano_corrente_aluno) {
                $a['turma_info'] = $turma_ano_corrente_aluno;
                $a['turma_id'] = $turma_ano_corrente_aluno['id'];
                // Buscar informações do curso
                $turma_completa = $this->turma->getById($turma_ano_corrente_aluno['id']);
                if ($turma_completa) {
                    $a['curso_nome'] = $turma_completa['curso_nome'] ?? '';
                    $a['curso_id'] = $turma_completa['curso_id'] ?? '';
                    $a['ano_curso'] = $turma_ano_corrente_aluno['ano_curso'];
                    $a['ano_civil'] = $turma_ano_corrente_aluno['ano_civil'];
                    $a['is_ano_corrente'] = ($turma_ano_corrente_aluno['ano_civil'] == $ano_corrente);
                }
            }
            
            // Contar eventos do aluno
            $a['total_eventos'] = $this->evento->countByAluno($a['id'], $registrado_por);
        }
        unset($a); // Limpar referência
        
        // Apply filters
        if ($filtro_curso) {
            $alunos = array_filter($alunos, function($a) use ($filtro_curso) {
                return !empty($a['curso_id']) && $a['curso_id'] == $filtro_curso;
            });
        }
        
        if ($filtro_turma) {
            $alunos = array_filter($alunos, function($a) use ($filtro_turma) {
                return !empty($a['turma_id']) && $a['turma_id'] == $filtro_turma;
            });
        }
        
        if ($filtro_nome) {
            $filtro_nome_lower = mb_strtolower($filtro_nome, 'UTF-8');
            $alunos = array_filter($alunos, function($a) use ($filtro_nome_lower) {
                $nome_lower = mb_strtolower($a['nome'], 'UTF-8');
                return mb_strpos($nome_lower, $filtro_nome_lower) !== false;
            });
        }
        
        // Re-index array after filtering
        $alunos = array_values($alunos);
        
        // Get aluno for editing if requested
        $aluno_edit = null;
        if (isset($_GET['edit'])) {
            $aluno_edit = $this->aluno->getById($_GET['edit']);
            if (!$aluno_edit) {
                $this->setError('Aluno não encontrado!');
            }
        }
        
        // Filter turmas by curso for dropdown
        $turmas_filtradas = $turmas_ano_corrente_lista;
        if ($filtro_curso) {
            $turmas_filtradas = array_filter($turmas_ano_corrente_lista, function($t) use ($filtro_curso) {
                return $t['curso_id'] == $filtro_curso;
            });
        }
        
        // Prepare view data
        $data = [
            'page_title' => 'Alunos',
            'success' => $this->getSuccess(),
            'error' => $this->getError(),
            'alunos' => $alunos,
            'cursos' => $cursos,
            'turmas_ano_corrente_lista' => $turmas_ano_corrente_lista,
            'turmas_filtradas' => $turmas_filtradas,
            'ano_corrente' => $ano_corrente,
            'filtro_curso' => $filtro_curso,
            'filtro_turma' => $filtro_turma,
            'filtro_nome' => $filtro_nome,
            'aluno_edit' => $aluno_edit,
            'user' => $this->user
        ];
        
        // Include header
        $page_title = 'Alunos';
        require_once __DIR__ . '/../includes/header.php';
        
        // Render view
        $this->render('alunos/index', $data);
        
        // Include footer
        require_once __DIR__ . '/../includes/footer.php';
    }
    
    /**
     * Handle POST requests
     */
    private function handlePost() {
        // Only admin can create/update/delete alunos
        if (!$this->user->isAdmin()) {
            $this->setError('Apenas administradores podem criar, editar ou excluir alunos.');
            $this->redirect('alunos.php');
            return;
        }
        
        if (!isset($_POST['action'])) {
            return;
        }
        
        $action = $_POST['action'];
        
        if ($action == 'create') {
            $this->create();
        } elseif ($action == 'update' && isset($_POST['id'])) {
            $this->update();
        } elseif ($action == 'delete' && isset($_POST['id'])) {
            $this->delete();
        }
    }
    
    /**
     * Create aluno
     */
    private function create() {
        $this->aluno->nome = $_POST['nome'] ?? '';
        $this->aluno->email = $_POST['email'] ?? '';
        $this->aluno->telefone_celular = $_POST['telefone_celular'] ?? '';
        
        if (empty($this->aluno->nome)) {
            $this->setError('Por favor, preencha o nome do aluno!');
            $this->redirect('alunos.php');
            return;
        }
        
        try {
            if ($this->aluno->create()) {
                $this->setSuccess('Aluno criado com sucesso!');
                $this->redirect('alunos.php?success=created');
            } else {
                $errorInfo = $this->db->errorInfo();
                $errorMsg = 'Erro ao criar aluno.';
                if (!empty($errorInfo[2])) {
                    $errorMsg .= ' Detalhes: ' . $errorInfo[2];
                }
                $this->setError($errorMsg);
                $this->redirect('alunos.php');
            }
        } catch (PDOException $e) {
            $this->setError('Erro ao criar aluno: ' . $e->getMessage());
            error_log("PDO Error ao criar aluno: " . $e->getMessage());
            $this->redirect('alunos.php');
        } catch (Exception $e) {
            $this->setError('Erro ao criar aluno: ' . $e->getMessage());
            error_log("Error ao criar aluno: " . $e->getMessage());
            $this->redirect('alunos.php');
        }
    }
    
    /**
     * Update aluno
     */
    private function update() {
        $this->aluno->id = $_POST['id'];
        $this->aluno->nome = $_POST['nome'] ?? '';
        $this->aluno->email = $_POST['email'] ?? '';
        $this->aluno->telefone_celular = $_POST['telefone_celular'] ?? '';
        
        if (empty($this->aluno->nome)) {
            $this->setError('Por favor, preencha o nome do aluno!');
            $this->redirect('alunos.php');
            return;
        }
        
        if ($this->aluno->update()) {
            $this->setSuccess('Aluno atualizado com sucesso!');
            $this->redirect('alunos.php?success=updated');
        } else {
            $this->setError('Erro ao atualizar aluno.');
            $this->redirect('alunos.php');
        }
    }
    
    /**
     * Delete aluno
     */
    private function delete() {
        $this->aluno->id = $_POST['id'];
        if ($this->aluno->delete()) {
            $this->setSuccess('Aluno excluído com sucesso!');
            $this->redirect('alunos.php?success=deleted');
        } else {
            $this->setError('Erro ao excluir aluno.');
            $this->redirect('alunos.php');
        }
    }
}

