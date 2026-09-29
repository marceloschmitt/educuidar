<?php
/**
 * Frequência por aluno e disciplina importada do SIGAA (tabela frequencia_disciplina).
 * "aulas" são períodos de aula, como o SIGAA conta.
 */
class FrequenciaDisciplina {
    /** Acima deste percentual de faltas a frequência fica abaixo do mínimo de 75%. */
    public const LIMITE_FALTAS = 25;

    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    /** SIGAA manda os nomes em maiúsculas: "EDUCAÇÃO FÍSICA II" vira "Educação Física II". */
    public static function nomeLegivel($nome) {
        $palavras = explode(' ', mb_convert_case(mb_strtolower(trim((string) $nome), 'UTF-8'), MB_CASE_TITLE, 'UTF-8'));
        $conectivos = ['a', 'o', 'e', 'de', 'da', 'do', 'das', 'dos', 'em', 'na', 'no', 'para', 'com'];
        foreach ($palavras as $i => $palavra) {
            $minuscula = mb_strtolower($palavra, 'UTF-8');
            if ($i > 0 && in_array($minuscula, $conectivos, true)) {
                $palavras[$i] = $minuscula;
            } elseif (preg_match('/^(i|ii|iii|iv|v|vi|vii|viii|ix|x)$/i', $palavra)) {
                $palavras[$i] = strtoupper($palavra);
            }
        }
        return implode(' ', $palavras);
    }

    /**
     * Disciplinas do aluno no ano, maior percentual de faltas primeiro.
     * Sem a tabela (coleta ainda não rodou), devolve [].
     */
    public function getPorAluno($aluno_id, $ano) {
        $query = "SELECT fd.cod_disciplina, fd.disciplina_nome, fd.aulas, fd.faltas, fd.presencas,
                         fd.percentual_frequencia, fd.ultima_aula, fd.atualizado_em,
                         ROUND(fd.faltas * 100 / fd.aulas, 1) AS percentual
                  FROM frequencia_disciplina fd
                  INNER JOIN alunos a ON a.id = fd.aluno_id
                  WHERE fd.aluno_id = :aluno_id
                    AND fd.ano = :ano
                    AND fd.aulas > 0
                    AND COALESCE(a.desistente, 0) = 0
                  ORDER BY fd.faltas / fd.aulas DESC, fd.disciplina_nome ASC";
        try {
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':aluno_id', (int) $aluno_id, PDO::PARAM_INT);
            $stmt->bindValue(':ano', (int) $ano, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
