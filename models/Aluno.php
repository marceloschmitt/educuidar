<?php
/**
 * Aluno model
 */

class Aluno {
    private $conn;
    private $table = 'alunos';

    public $id;
    public $nome;
    public $nome_social;
    public $email;
    public $telefone_celular;
    public $data_nascimento;
    public $numero_matricula;
    public $endereco;
    public $foto;
    public $pessoa_referencia;
    public $telefone_pessoa_referencia;
    public $rede_atendimento;
    public $auxilio_estudantil;
    public $nee;
    public $indigena;
    public $pei;
    public $profissionais_referencia;
    public $outras_observacoes;
    public $identidade_genero;
    public $grupo_familiar;
    public $guarda_legal;
    public $escolaridade_pais_responsaveis;
    public $necessidade_mudanca;
    public $meio_transporte;
    public $razao_escolha_ifrs;
    public $expectativa_estudante_familia;
    public $conhecimento_curso_tecnico;
    public $rede_atendimento_familia;
    public $estabelecimento_ensino_fundamental;
    public $monitoria_atendimento_reprovacao_fundamental;
    public $deficiencia_necessidade_especifica;
    public $necessidade_adequacao_aprendizagem;
    public $medidas_disciplinares;
    public $bullying;
    public $maiores_dificuldades;
    public $acesso_internet_casa;
    public $local_estudo;
    public $rotina_estudo_casa;
    public $habito_leitura;
    public $atividades_extracurriculares;
    public $acompanhamento_tratamento_especializado;
    public $alergias;
    public $medicacao_uso_continuo;
    public $situacao_marcante_vida;
    public $auxilios_direitos_estudantis;
    public $created_at;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create() {
        $query = "INSERT INTO " . $this->table . " 
                  (nome, nome_social, email, telefone_celular, data_nascimento, numero_matricula, endereco, foto, 
                   pessoa_referencia, telefone_pessoa_referencia, rede_atendimento, auxilio_estudantil, nee, 
                   indigena, pei, profissionais_referencia, outras_observacoes,
                   identidade_genero, grupo_familiar, guarda_legal, escolaridade_pais_responsaveis, necessidade_mudanca,
                   meio_transporte, razao_escolha_ifrs, expectativa_estudante_familia, conhecimento_curso_tecnico, rede_atendimento_familia,
                   estabelecimento_ensino_fundamental, monitoria_atendimento_reprovacao_fundamental, deficiencia_necessidade_especifica, necessidade_adequacao_aprendizagem,
                   medidas_disciplinares, bullying, maiores_dificuldades, acesso_internet_casa, local_estudo, rotina_estudo_casa, habito_leitura, atividades_extracurriculares,
                   acompanhamento_tratamento_especializado, alergias, medicacao_uso_continuo, situacao_marcante_vida, auxilios_direitos_estudantis) 
                  VALUES (:nome, :nome_social, :email, :telefone_celular, :data_nascimento, :numero_matricula, :endereco, :foto, 
                          :pessoa_referencia, :telefone_pessoa_referencia, :rede_atendimento, :auxilio_estudantil, :nee, 
                          :indigena, :pei, :profissionais_referencia, :outras_observacoes,
                          :identidade_genero, :grupo_familiar, :guarda_legal, :escolaridade_pais_responsaveis, :necessidade_mudanca,
                          :meio_transporte, :razao_escolha_ifrs, :expectativa_estudante_familia, :conhecimento_curso_tecnico, :rede_atendimento_familia,
                          :estabelecimento_ensino_fundamental, :monitoria_atendimento_reprovacao_fundamental, :deficiencia_necessidade_especifica, :necessidade_adequacao_aprendizagem,
                          :medidas_disciplinares, :bullying, :maiores_dificuldades, :acesso_internet_casa, :local_estudo, :rotina_estudo_casa, :habito_leitura, :atividades_extracurriculares,
                          :acompanhamento_tratamento_especializado, :alergias, :medicacao_uso_continuo, :situacao_marcante_vida, :auxilios_direitos_estudantis)";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':nome', $this->nome);
        $nome_social = !empty($this->nome_social) ? $this->nome_social : null;
        $stmt->bindParam(':nome_social', $nome_social);
        $email = !empty($this->email) ? $this->email : null;
        $stmt->bindParam(':email', $email);
        $telefone = !empty($this->telefone_celular) ? $this->telefone_celular : null;
        $stmt->bindParam(':telefone_celular', $telefone);
        $data_nascimento = !empty($this->data_nascimento) ? $this->data_nascimento : null;
        $stmt->bindParam(':data_nascimento', $data_nascimento);
        $numero_matricula = !empty($this->numero_matricula) ? $this->numero_matricula : null;
        $stmt->bindParam(':numero_matricula', $numero_matricula);
        $endereco = !empty($this->endereco) ? $this->endereco : null;
        $stmt->bindParam(':endereco', $endereco);
        $foto = !empty($this->foto) ? $this->foto : null;
        $stmt->bindParam(':foto', $foto);
        
        $pessoa_referencia = !empty($this->pessoa_referencia) ? $this->pessoa_referencia : null;
        $stmt->bindParam(':pessoa_referencia', $pessoa_referencia);
        $telefone_pessoa_referencia = !empty($this->telefone_pessoa_referencia) ? $this->telefone_pessoa_referencia : null;
        $stmt->bindParam(':telefone_pessoa_referencia', $telefone_pessoa_referencia);
        $rede_atendimento = !empty($this->rede_atendimento) ? $this->rede_atendimento : null;
        $stmt->bindParam(':rede_atendimento', $rede_atendimento);
        $auxilio_estudantil = isset($this->auxilio_estudantil) ? ($this->auxilio_estudantil ? 1 : 0) : 0;
        $stmt->bindParam(':auxilio_estudantil', $auxilio_estudantil, PDO::PARAM_INT);
        $nee = !empty($this->nee) ? $this->nee : null;
        $stmt->bindParam(':nee', $nee);
        $indigena = isset($this->indigena) ? ($this->indigena ? 1 : 0) : 0;
        $stmt->bindParam(':indigena', $indigena, PDO::PARAM_INT);
        $pei = isset($this->pei) ? ($this->pei ? 1 : 0) : 0;
        $stmt->bindParam(':pei', $pei, PDO::PARAM_INT);
        $profissionais_referencia = !empty($this->profissionais_referencia) ? $this->profissionais_referencia : null;
        $stmt->bindParam(':profissionais_referencia', $profissionais_referencia);
        $outras_observacoes = !empty($this->outras_observacoes) ? $this->outras_observacoes : null;
        $stmt->bindParam(':outras_observacoes', $outras_observacoes);
        $identidade_genero = !empty($this->identidade_genero) ? $this->identidade_genero : null;
        $stmt->bindParam(':identidade_genero', $identidade_genero);
        $grupo_familiar = !empty($this->grupo_familiar) ? $this->grupo_familiar : null;
        $stmt->bindParam(':grupo_familiar', $grupo_familiar);
        $guarda_legal = !empty($this->guarda_legal) ? $this->guarda_legal : null;
        $stmt->bindParam(':guarda_legal', $guarda_legal);
        $escolaridade_pais_responsaveis = !empty($this->escolaridade_pais_responsaveis) ? $this->escolaridade_pais_responsaveis : null;
        $stmt->bindParam(':escolaridade_pais_responsaveis', $escolaridade_pais_responsaveis);
        $necessidade_mudanca = !empty($this->necessidade_mudanca) ? $this->necessidade_mudanca : null;
        $stmt->bindParam(':necessidade_mudanca', $necessidade_mudanca);
        $meio_transporte = !empty($this->meio_transporte) ? $this->meio_transporte : null;
        $stmt->bindParam(':meio_transporte', $meio_transporte);
        $razao_escolha_ifrs = !empty($this->razao_escolha_ifrs) ? $this->razao_escolha_ifrs : null;
        $stmt->bindParam(':razao_escolha_ifrs', $razao_escolha_ifrs);
        $expectativa_estudante_familia = !empty($this->expectativa_estudante_familia) ? $this->expectativa_estudante_familia : null;
        $stmt->bindParam(':expectativa_estudante_familia', $expectativa_estudante_familia);
        $conhecimento_curso_tecnico = !empty($this->conhecimento_curso_tecnico) ? $this->conhecimento_curso_tecnico : null;
        $stmt->bindParam(':conhecimento_curso_tecnico', $conhecimento_curso_tecnico);
        $rede_atendimento_familia = !empty($this->rede_atendimento_familia) ? $this->rede_atendimento_familia : null;
        $stmt->bindParam(':rede_atendimento_familia', $rede_atendimento_familia);
        $estabelecimento_ensino_fundamental = !empty($this->estabelecimento_ensino_fundamental) ? $this->estabelecimento_ensino_fundamental : null;
        $stmt->bindParam(':estabelecimento_ensino_fundamental', $estabelecimento_ensino_fundamental);
        $monitoria_atendimento_reprovacao_fundamental = !empty($this->monitoria_atendimento_reprovacao_fundamental) ? $this->monitoria_atendimento_reprovacao_fundamental : null;
        $stmt->bindParam(':monitoria_atendimento_reprovacao_fundamental', $monitoria_atendimento_reprovacao_fundamental);
        $deficiencia_necessidade_especifica = !empty($this->deficiencia_necessidade_especifica) ? $this->deficiencia_necessidade_especifica : null;
        $stmt->bindParam(':deficiencia_necessidade_especifica', $deficiencia_necessidade_especifica);
        $necessidade_adequacao_aprendizagem = !empty($this->necessidade_adequacao_aprendizagem) ? $this->necessidade_adequacao_aprendizagem : null;
        $stmt->bindParam(':necessidade_adequacao_aprendizagem', $necessidade_adequacao_aprendizagem);
        $medidas_disciplinares = !empty($this->medidas_disciplinares) ? $this->medidas_disciplinares : null;
        $stmt->bindParam(':medidas_disciplinares', $medidas_disciplinares);
        $bullying = !empty($this->bullying) ? $this->bullying : null;
        $stmt->bindParam(':bullying', $bullying);
        $maiores_dificuldades = !empty($this->maiores_dificuldades) ? $this->maiores_dificuldades : null;
        $stmt->bindParam(':maiores_dificuldades', $maiores_dificuldades);
        $acesso_internet_casa = !empty($this->acesso_internet_casa) ? $this->acesso_internet_casa : null;
        $stmt->bindParam(':acesso_internet_casa', $acesso_internet_casa);
        $local_estudo = !empty($this->local_estudo) ? $this->local_estudo : null;
        $stmt->bindParam(':local_estudo', $local_estudo);
        $rotina_estudo_casa = !empty($this->rotina_estudo_casa) ? $this->rotina_estudo_casa : null;
        $stmt->bindParam(':rotina_estudo_casa', $rotina_estudo_casa);
        $habito_leitura = !empty($this->habito_leitura) ? $this->habito_leitura : null;
        $stmt->bindParam(':habito_leitura', $habito_leitura);
        $atividades_extracurriculares = !empty($this->atividades_extracurriculares) ? $this->atividades_extracurriculares : null;
        $stmt->bindParam(':atividades_extracurriculares', $atividades_extracurriculares);
        $acompanhamento_tratamento_especializado = !empty($this->acompanhamento_tratamento_especializado) ? $this->acompanhamento_tratamento_especializado : null;
        $stmt->bindParam(':acompanhamento_tratamento_especializado', $acompanhamento_tratamento_especializado);
        $alergias = !empty($this->alergias) ? $this->alergias : null;
        $stmt->bindParam(':alergias', $alergias);
        $medicacao_uso_continuo = !empty($this->medicacao_uso_continuo) ? $this->medicacao_uso_continuo : null;
        $stmt->bindParam(':medicacao_uso_continuo', $medicacao_uso_continuo);
        $situacao_marcante_vida = !empty($this->situacao_marcante_vida) ? $this->situacao_marcante_vida : null;
        $stmt->bindParam(':situacao_marcante_vida', $situacao_marcante_vida);
        $auxilios_direitos_estudantis = !empty($this->auxilios_direitos_estudantis) ? $this->auxilios_direitos_estudantis : null;
        $stmt->bindParam(':auxilios_direitos_estudantis', $auxilios_direitos_estudantis);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }
        return false;
    }

    public function getAll() {
        $query = "SELECT a.* FROM " . $this->table . " a
                  ORDER BY COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function getById($id) {
        $query = "SELECT a.* FROM " . $this->table . " a
                  WHERE a.id = :id LIMIT 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();

        return $stmt->fetch();
    }

    public function update() {
        $query = "UPDATE " . $this->table . " 
                  SET nome = :nome, 
                      nome_social = :nome_social,
                      email = :email,
                      telefone_celular = :telefone_celular,
                      data_nascimento = :data_nascimento,
                      numero_matricula = :numero_matricula,
                      endereco = :endereco,
                      foto = :foto,
                      pessoa_referencia = :pessoa_referencia,
                      telefone_pessoa_referencia = :telefone_pessoa_referencia,
                      rede_atendimento = :rede_atendimento,
                      auxilio_estudantil = :auxilio_estudantil,
                      nee = :nee,
                      indigena = :indigena,
                      pei = :pei,
                      profissionais_referencia = :profissionais_referencia,
                      outras_observacoes = :outras_observacoes,
                      identidade_genero = :identidade_genero,
                      grupo_familiar = :grupo_familiar,
                      guarda_legal = :guarda_legal,
                      escolaridade_pais_responsaveis = :escolaridade_pais_responsaveis,
                      necessidade_mudanca = :necessidade_mudanca,
                      meio_transporte = :meio_transporte,
                      razao_escolha_ifrs = :razao_escolha_ifrs,
                      expectativa_estudante_familia = :expectativa_estudante_familia,
                      conhecimento_curso_tecnico = :conhecimento_curso_tecnico,
                      rede_atendimento_familia = :rede_atendimento_familia,
                      estabelecimento_ensino_fundamental = :estabelecimento_ensino_fundamental,
                      monitoria_atendimento_reprovacao_fundamental = :monitoria_atendimento_reprovacao_fundamental,
                      deficiencia_necessidade_especifica = :deficiencia_necessidade_especifica,
                      necessidade_adequacao_aprendizagem = :necessidade_adequacao_aprendizagem,
                      medidas_disciplinares = :medidas_disciplinares,
                      bullying = :bullying,
                      maiores_dificuldades = :maiores_dificuldades,
                      acesso_internet_casa = :acesso_internet_casa,
                      local_estudo = :local_estudo,
                      rotina_estudo_casa = :rotina_estudo_casa,
                      habito_leitura = :habito_leitura,
                      atividades_extracurriculares = :atividades_extracurriculares,
                      acompanhamento_tratamento_especializado = :acompanhamento_tratamento_especializado,
                      alergias = :alergias,
                      medicacao_uso_continuo = :medicacao_uso_continuo,
                      situacao_marcante_vida = :situacao_marcante_vida,
                      auxilios_direitos_estudantis = :auxilios_direitos_estudantis
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);

        $stmt->bindParam(':id', $this->id);
        $stmt->bindParam(':nome', $this->nome);
        $nome_social = !empty($this->nome_social) ? $this->nome_social : null;
        $stmt->bindParam(':nome_social', $nome_social);
        $email = !empty($this->email) ? $this->email : null;
        $stmt->bindParam(':email', $email);
        $telefone = !empty($this->telefone_celular) ? $this->telefone_celular : null;
        $stmt->bindParam(':telefone_celular', $telefone);
        $data_nascimento = !empty($this->data_nascimento) ? $this->data_nascimento : null;
        $stmt->bindParam(':data_nascimento', $data_nascimento);
        $numero_matricula = !empty($this->numero_matricula) ? $this->numero_matricula : null;
        $stmt->bindParam(':numero_matricula', $numero_matricula);
        $endereco = !empty($this->endereco) ? $this->endereco : null;
        $stmt->bindParam(':endereco', $endereco);
        $foto = !empty($this->foto) ? $this->foto : null;
        $stmt->bindParam(':foto', $foto);
        
        $pessoa_referencia = !empty($this->pessoa_referencia) ? $this->pessoa_referencia : null;
        $stmt->bindParam(':pessoa_referencia', $pessoa_referencia);
        $telefone_pessoa_referencia = !empty($this->telefone_pessoa_referencia) ? $this->telefone_pessoa_referencia : null;
        $stmt->bindParam(':telefone_pessoa_referencia', $telefone_pessoa_referencia);
        $rede_atendimento = !empty($this->rede_atendimento) ? $this->rede_atendimento : null;
        $stmt->bindParam(':rede_atendimento', $rede_atendimento);
        $auxilio_estudantil = isset($this->auxilio_estudantil) ? ($this->auxilio_estudantil ? 1 : 0) : 0;
        $stmt->bindParam(':auxilio_estudantil', $auxilio_estudantil, PDO::PARAM_INT);
        $nee = !empty($this->nee) ? $this->nee : null;
        $stmt->bindParam(':nee', $nee);
        $indigena = isset($this->indigena) ? ($this->indigena ? 1 : 0) : 0;
        $stmt->bindParam(':indigena', $indigena, PDO::PARAM_INT);
        $pei = isset($this->pei) ? ($this->pei ? 1 : 0) : 0;
        $stmt->bindParam(':pei', $pei, PDO::PARAM_INT);
        $profissionais_referencia = !empty($this->profissionais_referencia) ? $this->profissionais_referencia : null;
        $stmt->bindParam(':profissionais_referencia', $profissionais_referencia);
        $outras_observacoes = !empty($this->outras_observacoes) ? $this->outras_observacoes : null;
        $stmt->bindParam(':outras_observacoes', $outras_observacoes);
        $identidade_genero = !empty($this->identidade_genero) ? $this->identidade_genero : null;
        $stmt->bindParam(':identidade_genero', $identidade_genero);
        $grupo_familiar = !empty($this->grupo_familiar) ? $this->grupo_familiar : null;
        $stmt->bindParam(':grupo_familiar', $grupo_familiar);
        $guarda_legal = !empty($this->guarda_legal) ? $this->guarda_legal : null;
        $stmt->bindParam(':guarda_legal', $guarda_legal);
        $escolaridade_pais_responsaveis = !empty($this->escolaridade_pais_responsaveis) ? $this->escolaridade_pais_responsaveis : null;
        $stmt->bindParam(':escolaridade_pais_responsaveis', $escolaridade_pais_responsaveis);
        $necessidade_mudanca = !empty($this->necessidade_mudanca) ? $this->necessidade_mudanca : null;
        $stmt->bindParam(':necessidade_mudanca', $necessidade_mudanca);
        $meio_transporte = !empty($this->meio_transporte) ? $this->meio_transporte : null;
        $stmt->bindParam(':meio_transporte', $meio_transporte);
        $razao_escolha_ifrs = !empty($this->razao_escolha_ifrs) ? $this->razao_escolha_ifrs : null;
        $stmt->bindParam(':razao_escolha_ifrs', $razao_escolha_ifrs);
        $expectativa_estudante_familia = !empty($this->expectativa_estudante_familia) ? $this->expectativa_estudante_familia : null;
        $stmt->bindParam(':expectativa_estudante_familia', $expectativa_estudante_familia);
        $conhecimento_curso_tecnico = !empty($this->conhecimento_curso_tecnico) ? $this->conhecimento_curso_tecnico : null;
        $stmt->bindParam(':conhecimento_curso_tecnico', $conhecimento_curso_tecnico);
        $rede_atendimento_familia = !empty($this->rede_atendimento_familia) ? $this->rede_atendimento_familia : null;
        $stmt->bindParam(':rede_atendimento_familia', $rede_atendimento_familia);
        $estabelecimento_ensino_fundamental = !empty($this->estabelecimento_ensino_fundamental) ? $this->estabelecimento_ensino_fundamental : null;
        $stmt->bindParam(':estabelecimento_ensino_fundamental', $estabelecimento_ensino_fundamental);
        $monitoria_atendimento_reprovacao_fundamental = !empty($this->monitoria_atendimento_reprovacao_fundamental) ? $this->monitoria_atendimento_reprovacao_fundamental : null;
        $stmt->bindParam(':monitoria_atendimento_reprovacao_fundamental', $monitoria_atendimento_reprovacao_fundamental);
        $deficiencia_necessidade_especifica = !empty($this->deficiencia_necessidade_especifica) ? $this->deficiencia_necessidade_especifica : null;
        $stmt->bindParam(':deficiencia_necessidade_especifica', $deficiencia_necessidade_especifica);
        $necessidade_adequacao_aprendizagem = !empty($this->necessidade_adequacao_aprendizagem) ? $this->necessidade_adequacao_aprendizagem : null;
        $stmt->bindParam(':necessidade_adequacao_aprendizagem', $necessidade_adequacao_aprendizagem);
        $medidas_disciplinares = !empty($this->medidas_disciplinares) ? $this->medidas_disciplinares : null;
        $stmt->bindParam(':medidas_disciplinares', $medidas_disciplinares);
        $bullying = !empty($this->bullying) ? $this->bullying : null;
        $stmt->bindParam(':bullying', $bullying);
        $maiores_dificuldades = !empty($this->maiores_dificuldades) ? $this->maiores_dificuldades : null;
        $stmt->bindParam(':maiores_dificuldades', $maiores_dificuldades);
        $acesso_internet_casa = !empty($this->acesso_internet_casa) ? $this->acesso_internet_casa : null;
        $stmt->bindParam(':acesso_internet_casa', $acesso_internet_casa);
        $local_estudo = !empty($this->local_estudo) ? $this->local_estudo : null;
        $stmt->bindParam(':local_estudo', $local_estudo);
        $rotina_estudo_casa = !empty($this->rotina_estudo_casa) ? $this->rotina_estudo_casa : null;
        $stmt->bindParam(':rotina_estudo_casa', $rotina_estudo_casa);
        $habito_leitura = !empty($this->habito_leitura) ? $this->habito_leitura : null;
        $stmt->bindParam(':habito_leitura', $habito_leitura);
        $atividades_extracurriculares = !empty($this->atividades_extracurriculares) ? $this->atividades_extracurriculares : null;
        $stmt->bindParam(':atividades_extracurriculares', $atividades_extracurriculares);
        $acompanhamento_tratamento_especializado = !empty($this->acompanhamento_tratamento_especializado) ? $this->acompanhamento_tratamento_especializado : null;
        $stmt->bindParam(':acompanhamento_tratamento_especializado', $acompanhamento_tratamento_especializado);
        $alergias = !empty($this->alergias) ? $this->alergias : null;
        $stmt->bindParam(':alergias', $alergias);
        $medicacao_uso_continuo = !empty($this->medicacao_uso_continuo) ? $this->medicacao_uso_continuo : null;
        $stmt->bindParam(':medicacao_uso_continuo', $medicacao_uso_continuo);
        $situacao_marcante_vida = !empty($this->situacao_marcante_vida) ? $this->situacao_marcante_vida : null;
        $stmt->bindParam(':situacao_marcante_vida', $situacao_marcante_vida);
        $auxilios_direitos_estudantis = !empty($this->auxilios_direitos_estudantis) ? $this->auxilios_direitos_estudantis : null;
        $stmt->bindParam(':auxilios_direitos_estudantis', $auxilios_direitos_estudantis);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function delete() {
        $query = "DELETE FROM " . $this->table . " WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $this->id);

        if ($stmt->execute()) {
            return true;
        }
        return false;
    }

    public function getTurmasAluno($aluno_id) {
        $query = "SELECT t.*, at.created_at as associado_em
                  FROM aluno_turmas at
                  INNER JOIN turmas t ON at.turma_id = t.id
                  WHERE at.aluno_id = :aluno_id
                  ORDER BY t.ano_civil DESC, t.ano_curso ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function adicionarTurmaAluno($aluno_id, $turma_id) {
        $query = "INSERT INTO aluno_turmas (aluno_id, turma_id) 
                  VALUES (:aluno_id, :turma_id)
                  ON DUPLICATE KEY UPDATE aluno_id = aluno_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);

        return $stmt->execute();
    }

    public function removerTurmaAluno($aluno_id, $turma_id) {
        $query = "DELETE FROM aluno_turmas WHERE aluno_id = :aluno_id AND turma_id = :turma_id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':aluno_id', $aluno_id);
        $stmt->bindParam(':turma_id', $turma_id);

        return $stmt->execute();
    }

    public function getAlunosTurmasAnoCorrente($ano_corrente, $turma_id = null, $curso_id = null) {
        $query = "SELECT DISTINCT a.*, 
                  t.id as turma_id, t.ano_civil, t.ano_curso,
                  c.id as curso_id, c.nome as curso_nome
                  FROM " . $this->table . " a
                  INNER JOIN aluno_turmas at ON a.id = at.aluno_id
                  INNER JOIN turmas t ON at.turma_id = t.id
                  INNER JOIN cursos c ON t.curso_id = c.id
                  WHERE t.ano_civil = :ano_corrente";
        
        $params = [':ano_corrente' => $ano_corrente];
        
        if ($turma_id) {
            $query .= " AND t.id = :turma_id";
            $params[':turma_id'] = $turma_id;
        }
        
        if ($curso_id) {
            $query .= " AND c.id = :curso_id";
            $params[':curso_id'] = $curso_id;
        }
        
        $query .= " ORDER BY c.nome ASC, t.ano_curso ASC, COALESCE(NULLIF(a.nome_social, ''), a.nome) ASC";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
?>

