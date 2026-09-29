<?php
require __DIR__ . '/../models/FrequenciaDisciplina.php';
$frequencias = json_decode(file_get_contents(__DIR__ . '/freq.json'), true);
$frequencia_geral = FrequenciaDisciplina::resumoGeral($frequencias);
$frequencia_atualizada_em = '2026-09-29 10:00:00';
$aluno_sel = ['nome' => 'Aluno Exemplo']; $alunos = [$aluno_sel]; $ano_corrente = 2026;
$meses = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
$eventos = [];
foreach (['2026-09-28','2026-09-24','2026-09-15','2026-08-30','2026-08-12','2026-06-27','2026-05-04'] as $i => $dt) $eventos[] = ['data_evento' => $dt, 'hora_evento' => $i % 2 ? '10:20:00' : null, 'tipo_evento_cor' => $i % 3 ? 'danger' : 'warning', 'tipo_evento_nome' => $i % 3 ? 'Falta' : 'Atraso', 'observacoes' => $i % 3 ? 'POA-INF217 - MATEMÁTICA II' : 'Chegou após o início da aula.'];
$page_title = 'Prévia'; $show_header = true; $responsavel_nome = 'Responsável Exemplo'; $main_max_width = '1140px';
require __DIR__ . '/../responsaveis/header.php';
$src = file_get_contents(__DIR__ . '/../responsaveis/index.php');
preg_match('/<\?php else: \?>\s*(<\?php \$nome = !empty\(\$aluno_sel.*)<\?php endif; \?>\s*<\?php require __DIR__ \. \'\/footer\.php\'; \?>/s', $src, $m);
eval('?>' . $m[1]);
echo '</main></body></html>';
