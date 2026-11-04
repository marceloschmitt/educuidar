<?php
/**
 * PDF do relatório de autorizações de entrada/saída (com data e horário).
 */
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/lib/fpdf/fpdf.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$autorizacao = new AutorizacaoResponsavel($db);

$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2();
if (!$user->isLoggedIn() || !$pode_ver) {
    header('Location: index.php');
    exit;
}

$tipos = AutorizacaoResponsavel::tiposLabels();
$meios = AutorizacaoResponsavel::meiosLabels();
$status_labels = AutorizacaoResponsavel::statusLabels();

$filtro_status = $_GET['status'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_data = $_GET['data'] ?? '';
$filtro_nome = trim($_GET['nome'] ?? '');

$filtros = [];
if ($filtro_status !== '') {
    $filtros['status'] = $filtro_status;
}
if ($filtro_tipo !== '') {
    $filtros['tipo'] = $filtro_tipo;
}
if ($filtro_data !== '') {
    $filtros['data'] = $filtro_data;
}
if ($filtro_nome !== '') {
    $filtros['nome'] = $filtro_nome;
}

$lista = $autorizacao->listForStaff($filtros);

$pdfTxt = static function ($texto) {
    $texto = (string) $texto;
    if ($texto === '') {
        return '';
    }
    if (function_exists('iconv')) {
        $convertido = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $texto);
        if ($convertido !== false) {
            return $convertido;
        }
    }
    return utf8_decode($texto);
};

class AutorizacoesEntradaSaidaPdf extends FPDF
{
    public function Header()
    {
    }

    public function Footer()
    {
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 8, (string) $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    public function NbLines($w, $txt)
    {
        $cw = $this->CurrentFont['cw'];
        if ($w == 0) {
            $w = $this->w - $this->rMargin - $this->x;
        }
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', (string) $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n") {
            $nb--;
        }
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ') {
                $sep = $i;
            }
            $l += $cw[$c] ?? 500;
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j) {
                        $i++;
                    }
                } else {
                    $i = $sep + 1;
                }
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else {
                $i++;
            }
        }
        return $nl;
    }

    public function TabelaLinha(array $widths, array $texts, array $aligns, $lineHeight = 5)
    {
        $nb = 1;
        foreach ($texts as $i => $txt) {
            $nb = max($nb, $this->NbLines($widths[$i], $txt));
        }
        $h = $lineHeight * $nb;
        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            return false;
        }
        $x = $this->GetX();
        $y = $this->GetY();
        foreach ($texts as $i => $txt) {
            $w = $widths[$i];
            $a = $aligns[$i] ?? 'L';
            $this->Rect($x, $y, $w, $h);
            $this->SetXY($x, $y);
            $this->MultiCell($w, $lineHeight, $txt, 0, $a);
            $x += $w;
            $this->SetXY($x, $y);
        }
        $this->SetY($y + $h);
        return true;
    }
}

$pdf = new AutorizacoesEntradaSaidaPdf('L', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(12, 12, 12);
$pdf->SetAutoPageBreak(true, 16);
$pdf->AddPage();

$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 7, $pdfTxt('Relatório — Autorizações de entrada e saída'), 0, 1);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 5, $pdfTxt('Gerado em: ' . date('d/m/Y H:i')), 0, 1);

$filtros_txt = [];
if ($filtro_status !== '') {
    $filtros_txt[] = 'Status: ' . ($status_labels[$filtro_status] ?? $filtro_status);
}
if ($filtro_tipo !== '') {
    $filtros_txt[] = 'Tipo: ' . ($tipos[$filtro_tipo] ?? $filtro_tipo);
}
if ($filtro_data !== '') {
    $filtros_txt[] = 'Data: ' . date('d/m/Y', strtotime($filtro_data));
}
if ($filtro_nome !== '') {
    $filtros_txt[] = 'Busca: ' . $filtro_nome;
}
if (!empty($filtros_txt)) {
    $pdf->Cell(0, 5, $pdfTxt('Filtros: ' . implode(' · ', $filtros_txt)), 0, 1);
}
$pdf->Cell(0, 5, $pdfTxt('Total: ' . count($lista) . ' registro(s)'), 0, 1);
$pdf->Ln(3);

$wDataHora = 32;
$wTipo = 40;
$wAluno = 50;
$wQuem = 48;
$wObs = 55;
$wStatus = 48;
$widths = [$wDataHora, $wTipo, $wAluno, $wQuem, $wObs, $wStatus];
$aligns = ['L', 'L', 'L', 'L', 'L', 'L'];

$imprimirCabecalho = static function ($pdf, $pdfTxt, $widths) {
    $pdf->SetFont('Arial', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    $headers = ['Data e horário', 'Tipo', 'Aluno', 'Quem autorizou', 'Observações', 'Status'];
    foreach ($headers as $i => $h) {
        $ln = ($i === count($headers) - 1) ? 1 : 0;
        $pdf->Cell($widths[$i], 6, $pdfTxt($h), 1, $ln, 'L', true);
    }
    $pdf->SetFont('Arial', '', 8);
};

$imprimirCabecalho($pdf, $pdfTxt, $widths);

if (empty($lista)) {
    $pdf->SetFont('Arial', 'I', 9);
    $pdf->Cell(0, 8, $pdfTxt('Nenhuma autorização encontrada com os filtros atuais.'), 0, 1);
} else {
    foreach ($lista as $item) {
        $nome_aluno = !empty($item['aluno_nome_social']) ? $item['aluno_nome_social'] : ($item['aluno_nome'] ?? '');
        $quem = AutorizacaoResponsavel::nomeQuemAutorizou($item);
        $escola = AutorizacaoResponsavel::ehRegistroEscola($item);
        if ($escola) {
            $meio = $meios[$item['meio_autorizacao'] ?? ''] ?? '';
            if ($meio !== '') {
                $quem .= ' (' . $meio . ')';
            }
        }
        $hora_txt = substr((string) ($item['hora'] ?? ''), 0, 5);
        $data_hora = '—';
        if (!empty($item['data_autorizacao'])) {
            $data_hora = date('d/m/Y', strtotime($item['data_autorizacao']));
            if ($hora_txt !== '') {
                $data_hora .= ' às ' . $hora_txt;
            }
        }
        $st = $item['status'] ?? 'pendente';
        $status_txt = $status_labels[$st] ?? $st;
        if (($st === 'ocorrido' || $st === 'nao_ocorrido') && !empty($item['confirmado_por_nome'])) {
            $status_txt .= "\n" . $item['confirmado_por_nome'];
            if (!empty($item['confirmado_em'])) {
                $status_txt .= "\n" . date('d/m/Y H:i', strtotime($item['confirmado_em']));
            }
        }
        $cells = [
            $pdfTxt($data_hora),
            $pdfTxt($tipos[$item['tipo'] ?? ''] ?? ($item['tipo'] ?? '')),
            $pdfTxt($nome_aluno),
            $pdfTxt($quem),
            $pdfTxt(trim((string) ($item['justificativa'] ?? ''))),
            $pdfTxt($status_txt),
        ];
        $ok = $pdf->TabelaLinha($widths, $cells, $aligns, 4);
        if (!$ok) {
            $imprimirCabecalho($pdf, $pdfTxt, $widths);
            $pdf->TabelaLinha($widths, $cells, $aligns, 4);
        }
    }
}

$nome_arquivo = 'entrada_saida_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $nome_arquivo);
exit;
