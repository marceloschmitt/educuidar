<?php
/**
 * Usuarios Controller
 */

class UsuariosController extends Controller {
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Lista de usuários
     */
    public function index() {
        $this->requireAdmin();
        
        // Process POST requests
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->handlePost();
            return;
        }
        
        // Get success/error messages from session
        $success = $this->getSuccess();
        $error = $this->getError();
        $resultado_professores = $_SESSION['inserir_professores_resultado'] ?? null;
        unset($_SESSION['inserir_professores_resultado']);
        
        // Get all users
        $usuarios = $this->user->getAll();
        $cursos_coordenacao_map = $this->user->getCursosCoordenadosPorUsuarios();
        foreach ($usuarios as &$usr) {
            $usr['cursos_coordenados'] = $cursos_coordenacao_map[(int)$usr['id']] ?? [];
        }
        unset($usr);

        $user_types = $this->user->getUserTypes();
        $cursos = (new Curso($this->db))->getAll();
        
        // Include header
        $page_title = 'Usuários';
        require_once __DIR__ . '/../includes/header.php';
        
        // Render view
        $this->render('usuarios/index', [
            'success' => $success,
            'error' => $error,
            'usuarios' => $usuarios,
            'user_types' => $user_types,
            'cursos' => $cursos,
            'resultado_professores' => $resultado_professores,
            'user' => $this->user
        ]);
        
        // Include footer
        require_once __DIR__ . '/../includes/footer.php';
    }
    
    /**
     * Handle POST requests
     */
    private function handlePost() {
        $action = $_POST['action'] ?? 'create';
        
        if ($action == 'create') {
            $this->create();
        } elseif ($action == 'update') {
            $this->update();
        } elseif ($action == 'delete') {
            $this->delete();
        } elseif ($action == 'inserir_professores') {
            $this->inserirProfessores();
        }
    }

    /**
     * Roda python/inserir_professores.py e guarda a saída na sessão
     */
    private function inserirProfessores() {
        $tipo_id = (int) ($_POST['tipo_usuario_id'] ?? 0);
        $simular = !empty($_POST['simular']);

        $tipos_validos = array_map('intval', array_column($this->user->getUserTypes(), 'id'));
        if (!in_array($tipo_id, $tipos_validos, true)) {
            $this->setError('Escolha o tipo de usuário dos professores.');
            $this->redirect('usuarios.php');
            return;
        }

        if (!function_exists('proc_open')) {
            $this->setError('O PHP deste servidor não permite executar scripts (proc_open desabilitado).');
            $this->redirect('usuarios.php');
            return;
        }

        $dir_python = realpath(__DIR__ . '/../python');
        $comando = [$this->localizarPython(), $dir_python . '/inserir_professores.py', '--tipo-usuario', (string) $tipo_id];
        if ($simular) {
            $comando[] = '--dry-run';
        }

        set_time_limit(300);
        $ambiente = array_merge(getenv(), ['PYTHONIOENCODING' => 'utf-8']);
        $processo = proc_open($comando, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $dir_python, $ambiente);
        if (!is_resource($processo)) {
            $this->setError('Não foi possível iniciar o script inserir_professores.py.');
            $this->redirect('usuarios.php');
            return;
        }

        $saida = stream_get_contents($pipes[1]);
        $erros = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $codigo = proc_close($processo);

        $_SESSION['inserir_professores_resultado'] = [
            'simulacao' => $simular,
            'codigo' => $codigo,
            'saida' => trim((string) $saida),
            'erros' => trim((string) $erros),
        ];
        $this->redirect('usuarios.php');
    }

    private function localizarPython() {
        foreach (['/usr/bin/python3', '/usr/local/bin/python3', '/opt/homebrew/bin/python3'] as $caminho) {
            if (is_executable($caminho)) {
                return $caminho;
            }
        }
        return 'python3';
    }
    
    /**
     * Create a new user
     */
    private function create() {
        $this->user->username = $_POST['username'] ?? '';
        $this->user->email = $_POST['email'] ?? '';
        $this->user->password = $_POST['password'] ?? '';
        $this->user->full_name = $_POST['full_name'] ?? '';
        $this->user->user_type_id = $_POST['user_type_id'] ?? '';
        $this->user->auth_type = $_POST['auth_type'] ?? '';
        
        // Validar campos obrigatórios
        if (empty($this->user->username) || empty($this->user->email) || empty($this->user->full_name) || empty($this->user->user_type_id) || empty($this->user->auth_type)) {
            $this->setError('Por favor, preencha todos os campos obrigatórios!');
            $this->redirect('usuarios.php');
            return;
        } elseif ($this->user->auth_type === 'local' && empty($this->user->password)) {
            $this->setError('Senha é obrigatória para autenticação local!');
            $this->redirect('usuarios.php');
            return;
        }
        
        if ($this->user->create()) {
            $this->user->setCursosCoordenados($this->user->id, $this->parseCursosCoordenadosFromPost());
            $this->setSuccess('Usuário criado com sucesso!');
        } else {
            $this->setError('Erro ao criar usuário. Tente novamente.');
        }
        
        $this->redirect('usuarios.php');
    }
    
    /**
     * Update an existing user
     */
    private function update() {
        $this->user->id = $_POST['id'] ?? null;
        $this->user->username = $_POST['username'] ?? '';
        $this->user->email = $_POST['email'] ?? '';
        $this->user->full_name = $_POST['full_name'] ?? '';
        $this->user->user_type_id = $_POST['user_type_id'] ?? '';
        $this->user->auth_type = $_POST['auth_type'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if (empty($this->user->id)) {
            $this->setError('ID do usuário não fornecido.');
            $this->redirect('usuarios.php');
            return;
        }
        
        if (empty($this->user->username) || empty($this->user->email) || empty($this->user->full_name) || empty($this->user->user_type_id) || empty($this->user->auth_type)) {
            $this->setError('Por favor, preencha todos os campos obrigatórios!');
            $this->redirect('usuarios.php');
            return;
        }
        
        if ($this->user->update()) {
            $this->user->setCursosCoordenados($this->user->id, $this->parseCursosCoordenadosFromPost());
            // Update password if provided and using local auth
            if (!empty($new_password) && $this->user->auth_type === 'local') {
                if ($this->user->updatePassword($this->user->id, $new_password)) {
                    $this->setSuccess('Usuário e senha atualizados com sucesso!');
                } else {
                    $this->setSuccess('Usuário atualizado, mas houve erro ao atualizar a senha.');
                }
            } else {
                $this->setSuccess('Usuário atualizado com sucesso!');
            }
        } else {
            $this->setError('Erro ao atualizar usuário. Tente novamente.');
        }
        
        $this->redirect('usuarios.php');
    }

    /**
     * Delete a user
     */
    private function delete() {
        $user_id = $_POST['id'] ?? null;
        $current_user_id = $_SESSION['user_id'] ?? null;

        if (empty($user_id)) {
            $this->setError('ID do usuário não fornecido.');
            $this->redirect('usuarios.php');
            return;
        }

        if ($current_user_id && (int)$user_id === (int)$current_user_id) {
            $this->setError('Não é possível excluir o usuário corrente.');
            $this->redirect('usuarios.php');
            return;
        }

        if ($this->user->delete($user_id)) {
            $this->setSuccess('Usuário excluído com sucesso!');
        } else {
            $this->setError('Erro ao excluir usuário. Tente novamente.');
        }

        $this->redirect('usuarios.php');
    }

    private function parseCursosCoordenadosFromPost() {
        $ids = $_POST['cursos_coordenacao'] ?? [];
        if (!is_array($ids)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        })));
    }
}

