<?php
/**
 * Alunos Controller
 */

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
            
            // Incluir todas as turmas com informações completas
            $a['todas_turmas'] = [];
            foreach ($turmas_aluno as $ta) {
                $turma_completa = $this->turma->getById($ta['id']);
                if ($turma_completa) {
                    $a['todas_turmas'][] = [
                        'id' => $ta['id'],
                        'curso_nome' => $turma_completa['curso_nome'] ?? '',
                        'curso_id' => $turma_completa['curso_id'] ?? '',
                        'ano_curso' => $ta['ano_curso'],
                        'ano_civil' => $ta['ano_civil'],
                        'is_ano_corrente' => ($ta['ano_civil'] == $ano_corrente)
                    ];
                }
            }
            
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
                $nome_lower = mb_strtolower($a['nome'] ?? '', 'UTF-8');
                $nome_social_lower = mb_strtolower($a['nome_social'] ?? '', 'UTF-8');
                $nome_exibicao_lower = !empty($a['nome_social']) ? $nome_social_lower : $nome_lower;
                return mb_strpos($nome_lower, $filtro_nome_lower) !== false || 
                       mb_strpos($nome_social_lower, $filtro_nome_lower) !== false ||
                       mb_strpos($nome_exibicao_lower, $filtro_nome_lower) !== false;
            });
        }
        
        // Re-index array after filtering
        $alunos = array_values($alunos);
        
        // Sort by nome_social if exists, otherwise by nome
        usort($alunos, function($a, $b) {
            $nome_a = !empty($a['nome_social']) ? $a['nome_social'] : $a['nome'];
            $nome_b = !empty($b['nome_social']) ? $b['nome_social'] : $b['nome'];
            return strcasecmp($nome_a, $nome_b);
        });
        
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
        
        $return_to = $_GET['return_to'] ?? '';

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
            'return_to' => $return_to,
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
        // Only admin and assistencia_estudantil can create/update/delete alunos
        if (!$this->user->isAdmin() && !$this->user->isAssistenciaEstudantil()) {
            $this->setError('Apenas administradores e assistência estudantil podem criar, editar ou excluir alunos.');
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
     * Handle photo upload
     */
    private function uploadFoto($aluno_id = null) {
        if (!isset($_FILES['foto']) || $_FILES['foto']['error'] !== UPLOAD_ERR_OK) {
            return null;
        }
        
        $upload_dir = __DIR__ . '/../uploads/fotos/';
        if (!is_dir($upload_dir)) {
            if (!mkdir($upload_dir, 0755, true)) {
                error_log("Erro ao criar diretório de uploads: " . $upload_dir);
                return null;
            }
        }
        
        $file = $_FILES['foto'];
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file['type'], $allowed_types)) {
            $this->setError('Tipo de arquivo não permitido. Use apenas imagens (JPG, PNG, GIF).');
            return null;
        }
        
        if ($file['size'] > $max_size) {
            $this->setError('Arquivo muito grande. Tamanho máximo: 5MB.');
            return null;
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'aluno_' . ($aluno_id ?? time()) . '_' . uniqid() . '.' . $extension;
        $filepath = $upload_dir . $filename;
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            return 'uploads/fotos/' . $filename;
        }
        
        return null;
    }
    
    /**
     * Delete old photo if exists
     */
    private function deleteFoto($foto_path) {
        if (!empty($foto_path)) {
            $filepath = __DIR__ . '/../' . $foto_path;
            if (file_exists($filepath)) {
                @unlink($filepath);
            }
        }
    }
    
    /**
     * Create aluno
     */
    private function create() {
        $this->aluno->nome = $_POST['nome'] ?? '';
        $this->aluno->nome_social = $_POST['nome_social'] ?? '';
        $this->aluno->email = $_POST['email'] ?? '';
        $this->aluno->telefone_celular = $_POST['telefone_celular'] ?? '';
        $this->aluno->data_nascimento = $_POST['data_nascimento'] ?? '';
        $this->aluno->numero_matricula = $_POST['numero_matricula'] ?? '';
        $this->aluno->endereco = $_POST['endereco'] ?? '';
        $this->aluno->pessoa_referencia = $_POST['pessoa_referencia'] ?? '';
        $this->aluno->telefone_pessoa_referencia = $_POST['telefone_pessoa_referencia'] ?? '';
        $this->aluno->rede_atendimento = $_POST['rede_atendimento'] ?? '';
        $this->aluno->auxilio_estudantil = isset($_POST['auxilio_estudantil']) ? ($_POST['auxilio_estudantil'] == '1') : false;
        $this->aluno->nee = $_POST['nee'] ?? '';
        $this->aluno->indigena = isset($_POST['indigena']) ? ($_POST['indigena'] == '1') : false;
        $this->aluno->pei = isset($_POST['pei']) ? ($_POST['pei'] == '1') : false;
        $this->aluno->profissionais_referencia = $_POST['profissionais_referencia'] ?? '';
        $this->aluno->outras_observacoes = $_POST['outras_observacoes'] ?? '';
        if ($this->user->isAssistenciaEstudantil()) {
            $this->aluno->identidade_genero = $_POST['identidade_genero'] ?? '';
            $this->aluno->grupo_familiar = $_POST['grupo_familiar'] ?? '';
            $this->aluno->guarda_legal = $_POST['guarda_legal'] ?? '';
            $this->aluno->escolaridade_pais_responsaveis = $_POST['escolaridade_pais_responsaveis'] ?? '';
            $this->aluno->necessidade_mudanca = $_POST['necessidade_mudanca'] ?? '';
            $this->aluno->meio_transporte = $_POST['meio_transporte'] ?? '';
            $this->aluno->razao_escolha_ifrs = $_POST['razao_escolha_ifrs'] ?? '';
            $this->aluno->expectativa_estudante_familia = $_POST['expectativa_estudante_familia'] ?? '';
            $this->aluno->conhecimento_curso_tecnico = $_POST['conhecimento_curso_tecnico'] ?? '';
            $this->aluno->rede_atendimento_familia = $_POST['rede_atendimento_familia'] ?? '';
        } else {
            $this->aluno->identidade_genero = null;
            $this->aluno->grupo_familiar = null;
            $this->aluno->guarda_legal = null;
            $this->aluno->escolaridade_pais_responsaveis = null;
            $this->aluno->necessidade_mudanca = null;
            $this->aluno->meio_transporte = null;
            $this->aluno->razao_escolha_ifrs = null;
            $this->aluno->expectativa_estudante_familia = null;
            $this->aluno->conhecimento_curso_tecnico = null;
            $this->aluno->rede_atendimento_familia = null;
        }
        
        if (empty($this->aluno->nome)) {
            $this->setError('Por favor, preencha o nome do aluno!');
            $this->redirect('alunos.php');
            return;
        }
        
        try {
            // Upload foto antes de criar o aluno
            $foto_path = $this->uploadFoto();
            if ($foto_path === null && isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
                // Se houve erro no upload e não foi "arquivo não enviado", já foi setado o erro
                $this->redirect('alunos.php');
                return;
            }
            $this->aluno->foto = $foto_path;
            
            if ($this->aluno->create()) {
                $this->setSuccess('Aluno criado com sucesso!');
                $this->redirect('alunos.php?success=created');
            } else {
                // Se falhou ao criar, deletar foto enviada
                if ($foto_path) {
                    $this->deleteFoto($foto_path);
                }
                $errorInfo = $this->db->errorInfo();
                $errorMsg = 'Erro ao criar aluno.';
                if (!empty($errorInfo[2])) {
                    $errorMsg .= ' Detalhes: ' . $errorInfo[2];
                }
                $this->setError($errorMsg);
                $this->redirect('alunos.php');
            }
        } catch (PDOException $e) {
            // Se falhou, deletar foto enviada
            if (isset($foto_path) && $foto_path) {
                $this->deleteFoto($foto_path);
            }
            $this->setError('Erro ao criar aluno: ' . $e->getMessage());
            error_log("PDO Error ao criar aluno: " . $e->getMessage());
            $this->redirect('alunos.php');
        } catch (Exception $e) {
            // Se falhou, deletar foto enviada
            if (isset($foto_path) && $foto_path) {
                $this->deleteFoto($foto_path);
            }
            $this->setError('Erro ao criar aluno: ' . $e->getMessage());
            error_log("Error ao criar aluno: " . $e->getMessage());
            $this->redirect('alunos.php');
        }
    }
    
    /**
     * Update aluno
     */
    private function update() {
        $return_to = $_POST['return_to'] ?? '';
        $return_to = trim($return_to);
        if ($return_to !== '') {
            if (preg_match('/[\r\n]/', $return_to) || strpos($return_to, '://') !== false || strpos($return_to, '//') === 0) {
                $return_to = '';
            }
        }

        $this->aluno->id = $_POST['id'];
        $this->aluno->nome = $_POST['nome'] ?? '';
        $this->aluno->nome_social = $_POST['nome_social'] ?? '';
        $this->aluno->email = $_POST['email'] ?? '';
        $this->aluno->telefone_celular = $_POST['telefone_celular'] ?? '';
        $this->aluno->data_nascimento = $_POST['data_nascimento'] ?? '';
        $this->aluno->numero_matricula = $_POST['numero_matricula'] ?? '';
        $this->aluno->endereco = $_POST['endereco'] ?? '';
        $this->aluno->pessoa_referencia = $_POST['pessoa_referencia'] ?? '';
        $this->aluno->telefone_pessoa_referencia = $_POST['telefone_pessoa_referencia'] ?? '';
        $this->aluno->rede_atendimento = $_POST['rede_atendimento'] ?? '';
        $this->aluno->auxilio_estudantil = isset($_POST['auxilio_estudantil']) ? ($_POST['auxilio_estudantil'] == '1') : false;
        $this->aluno->nee = $_POST['nee'] ?? '';
        $this->aluno->indigena = isset($_POST['indigena']) ? ($_POST['indigena'] == '1') : false;
        $this->aluno->pei = isset($_POST['pei']) ? ($_POST['pei'] == '1') : false;
        $this->aluno->profissionais_referencia = $_POST['profissionais_referencia'] ?? '';
        $this->aluno->outras_observacoes = $_POST['outras_observacoes'] ?? '';
        
        if (empty($this->aluno->nome)) {
            $this->setError('Por favor, preencha o nome do aluno!');
            $this->redirect($return_to !== '' ? $return_to : 'alunos.php');
            return;
        }
        
        $is_assistencia = $this->user->isAssistenciaEstudantil();

        // Get current aluno data to check for existing photo
        $aluno_atual = $this->aluno->getById($this->aluno->id);
        $foto_antiga = $aluno_atual['foto'] ?? null;

        if ($is_assistencia) {
            $this->aluno->identidade_genero = $_POST['identidade_genero'] ?? '';
            $this->aluno->grupo_familiar = $_POST['grupo_familiar'] ?? '';
            $this->aluno->guarda_legal = $_POST['guarda_legal'] ?? '';
            $this->aluno->escolaridade_pais_responsaveis = $_POST['escolaridade_pais_responsaveis'] ?? '';
            $this->aluno->necessidade_mudanca = $_POST['necessidade_mudanca'] ?? '';
            $this->aluno->meio_transporte = $_POST['meio_transporte'] ?? '';
            $this->aluno->razao_escolha_ifrs = $_POST['razao_escolha_ifrs'] ?? '';
            $this->aluno->expectativa_estudante_familia = $_POST['expectativa_estudante_familia'] ?? '';
            $this->aluno->conhecimento_curso_tecnico = $_POST['conhecimento_curso_tecnico'] ?? '';
            $this->aluno->rede_atendimento_familia = $_POST['rede_atendimento_familia'] ?? '';
        } else {
            $this->aluno->identidade_genero = $aluno_atual['identidade_genero'] ?? null;
            $this->aluno->grupo_familiar = $aluno_atual['grupo_familiar'] ?? null;
            $this->aluno->guarda_legal = $aluno_atual['guarda_legal'] ?? null;
            $this->aluno->escolaridade_pais_responsaveis = $aluno_atual['escolaridade_pais_responsaveis'] ?? null;
            $this->aluno->necessidade_mudanca = $aluno_atual['necessidade_mudanca'] ?? null;
            $this->aluno->meio_transporte = $aluno_atual['meio_transporte'] ?? null;
            $this->aluno->razao_escolha_ifrs = $aluno_atual['razao_escolha_ifrs'] ?? null;
            $this->aluno->expectativa_estudante_familia = $aluno_atual['expectativa_estudante_familia'] ?? null;
            $this->aluno->conhecimento_curso_tecnico = $aluno_atual['conhecimento_curso_tecnico'] ?? null;
            $this->aluno->rede_atendimento_familia = $aluno_atual['rede_atendimento_familia'] ?? null;
        }
        
        // Handle photo upload
        $foto_path = $this->uploadFoto($this->aluno->id);
        if ($foto_path === null && isset($_FILES['foto']) && $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Se houve erro no upload e não foi "arquivo não enviado", já foi setado o erro
            $this->redirect($return_to !== '' ? $return_to : 'alunos.php');
            return;
        }
        
        // Se nova foto foi enviada, usar ela; senão, manter a antiga
        if ($foto_path) {
            $this->aluno->foto = $foto_path;
            // Delete old photo if exists
            if ($foto_antiga) {
                $this->deleteFoto($foto_antiga);
            }
        } else {
            // Keep existing photo
            $this->aluno->foto = $foto_antiga;
        }
        
        // Check if user wants to delete photo
        if (isset($_POST['remover_foto']) && $_POST['remover_foto'] == '1') {
            if ($foto_antiga) {
                $this->deleteFoto($foto_antiga);
            }
            $this->aluno->foto = null;
        }
        
        if ($this->aluno->update()) {
            $this->setSuccess('Aluno atualizado com sucesso!');
            $this->redirect($return_to !== '' ? $return_to : 'alunos.php?success=updated');
        } else {
            // Se falhou ao atualizar, deletar nova foto se foi enviada
            if ($foto_path && $foto_path !== $foto_antiga) {
                $this->deleteFoto($foto_path);
            }
            $this->setError('Erro ao atualizar aluno.');
            $this->redirect($return_to !== '' ? $return_to : 'alunos.php');
        }
    }
    
    /**
     * Delete aluno
     */
    private function delete() {
        $this->aluno->id = $_POST['id'];
        
        // Get aluno data to delete photo
        $aluno_data = $this->aluno->getById($this->aluno->id);
        if ($aluno_data && !empty($aluno_data['foto'])) {
            $this->deleteFoto($aluno_data['foto']);
        }
        
        if ($this->aluno->delete()) {
            $this->setSuccess('Aluno excluído com sucesso!');
            $this->redirect('alunos.php?success=deleted');
        } else {
            $this->setError('Erro ao excluir aluno.');
            $this->redirect('alunos.php');
        }
    }
}

