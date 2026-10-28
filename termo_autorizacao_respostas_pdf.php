<?php
/**
 * PDF das autorizações do termo (gerado no servidor — sem cabeçalho/rodapé do navegador).
 */
require_once __DIR__ . '/config/init.php';
require_once __DIR__ . '/lib/fpdf/fpdf.php';

$database = new Database();
$db = $database->getConnection();
$user = new User($db);
$termoModel = new TermoAutorizacao($db);

$pode_ver = $user->isAdmin() || $user->isNivel0() || $user->isNivel1() || $user->isNivel2();
if (!$user->isLoggedIn() || !$pode_ver) {
    header('Location: index.php');
    exit;
}

$termo_id = (int) ($_GET['id'] ?? 0);
$termo = $termo_id ? $termoModel->getById($termo_id) : null;
if (!$termo) {
    $_SESSION['error'] = 'Autorização não encontrada.';
    header('Location: termos_autorizacao.php');
    exit;
}

$listas = $termoModel->montarListasDecisoesParaPdf($termo_id);
$turmas_label = [];
foreach ($termo['turmas'] ?? [] as $tu) {
    $turmas_label[] = ($tu['curso_nome'] ?? '') . ' - ' . (int) ($tu['ano_curso'] ?? 0) . 'º';
}

$fmtData = static function ($valor) {
    if (empty($valor)) {
        return '—';
    }
    $ts = strtotime($valor);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
};

$fmtResponsavel = static function (array $linha) {
    $nome = trim((string) ($linha['responsavel_nome'] ?? '—'));
    $parentesco = trim((string) ($linha['parentesco'] ?? ''));
    if ($parentesco !== '' && $nome !== '—') {
        return $nome . ' (' . $parentesco . ')';
    }
    return $nome !== '' ? $nome : '—';
};

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

$secoes = [
    ['titulo' => 'Discordaram', 'itens' => $listas['discordaram']],
    ['titulo' => 'Concordaram', 'itens' => $listas['concordaram']],
    ['titulo' => 'Não responderam', 'itens' => $listas['nao_responderam']],
];

class TermoAutorizacoesPdf extends FPDF
{
    public function Header()
    {
        // Sem cabeçalho (evita repetir título/data em todas as páginas).
    }

    public function Footer()
    {
        // Sem rodapé com URL; apenas número da página, discreto.
        $this->SetY(-12);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(0, 8, (string) $this->PageNo() . '/{nb}', 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    /** Quantidade de linhas que o texto ocupa na largura $w. */
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

    /**
     * Linha de tabela com quebra de texto (evita estouro de coluna).
     * @param array $widths larguras
     * @param array $texts  textos já convertidos para Latin-1
     * @param array $aligns alinhamentos (R/L/C)
     */
    public function TabelaLinha(array $widths, array $texts, array $aligns, $lineHeight = 5)
    {
        $nb = 1;
        foreach ($texts as $i => $txt) {
            $nb = max($nb, $this->NbLines($widths[$i], $txt));
        }
        $h = $lineHeight * $nb;

        if ($this->GetY() + $h > $this->PageBreakTrigger) {
            $this->AddPage($this->CurOrientation);
            return false; // chamador deve redesenhar cabeçalho e repetir a linha
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

$pdf = new TermoAutorizacoesPdf('P', 'mm', 'A4');
$pdf->AliasNbPages();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 18);
$pdf->AddPage();

$titulo = 'Autorizações — ' . ($termo['titulo'] ?? '');
$pdf->SetFont('Arial', 'B', 14);
$pdf->MultiCell(0, 7, $pdfTxt($titulo), 0, 'L');
$pdf->Ln(2);

$pdf->SetFont('Arial', '', 10);
$data_evento = date('d/m/Y', strtotime($termo['data_evento']));
if (!empty($termo['hora'])) {
    $data_evento .= ' às ' . substr($termo['hora'], 0, 5);
}
$pdf->Cell(0, 5, $pdfTxt('Data do evento: ' . $data_evento), 0, 1);
$pdf->Cell(0, 5, $pdfTxt('Turmas: ' . (!empty($turmas_label) ? implode(', ', $turmas_label) : '—')), 0, 1);
$pdf->Cell(0, 5, $pdfTxt('Gerado em: ' . date('d/m/Y H:i')), 0, 1);
$pdf->Ln(4);

$wNum = 10;
$wAluno = 58;
$wResp = 78;
$wData = 34;
$wTotal = $wNum + $wAluno + $wResp + $wData;
$widths = [$wNum, $wAluno, $wResp, $wData];
$aligns = ['R', 'L', 'L', 'L'];

$imprimirCabecalhoTurma = static function ($pdf, $pdfTxt, $turma, $wTotal, $wNum, $wAluno, $wResp, $wData) {
    $pdf->SetFont('Arial', 'B', 9);
    $pdf->SetFillColor(220, 230, 240);
    $pdf->Cell($wTotal, 6, $pdfTxt($turma), 1, 1, 'L', true);
    $pdf->SetFillColor(240, 240, 240);
    $pdf->Cell($wNum, 6, $pdfTxt('Nº'), 1, 0, 'R', true);
    $pdf->Cell($wAluno, 6, $pdfTxt('Aluno'), 1, 0, 'L', true);
    $pdf->Cell($wResp, 6, $pdfTxt('Responsável'), 1, 0, 'L', true);
    $pdf->Cell($wData, 6, $pdfTxt('Data'), 1, 1, 'L', true);
    $pdf->SetFont('Arial', '', 8);
};

foreach ($secoes as $secao) {
    if ($pdf->GetY() > 250) {
        $pdf->AddPage();
    }

    $pdf->SetFont('Arial', 'B', 11);
    $pdf->Cell(0, 7, $pdfTxt($secao['titulo'] . ' (' . count($secao['itens']) . ')'), 0, 1);
    $pdf->Ln(1);

    if (empty($secao['itens'])) {
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->Cell(0, 5, $pdfTxt('Nenhum registro nesta lista.'), 0, 1);
        $pdf->Ln(4);
        continue;
    }

    $turma_atual = null;
    $n = 0;
    foreach ($secao['itens'] as $linha) {
        $turma = (string) ($linha['turma_label'] ?? '—');
        if ($turma !== $turma_atual) {
            if ($pdf->GetY() > 250) {
                $pdf->AddPage();
            }
            $imprimirCabecalhoTurma($pdf, $pdfTxt, $turma, $wTotal, $wNum, $wAluno, $wResp, $wData);
            $turma_atual = $turma;
        }

        $n++;
        $cells = [
            (string) $n,
            $pdfTxt($linha['aluno_nome'] ?? ''),
            $pdfTxt($fmtResponsavel($linha)),
            $pdfTxt($fmtData($linha['decidido_em'] ?? null)),
        ];

        $ok = $pdf->TabelaLinha($widths, $cells, $aligns, 4.5);
        if (!$ok) {
            $imprimirCabecalhoTurma($pdf, $pdfTxt, $turma, $wTotal, $wNum, $wAluno, $wResp, $wData);
            $pdf->TabelaLinha($widths, $cells, $aligns, 4.5);
        }
    }
    $pdf->Ln(5);
}

$nome_arquivo = 'autorizacoes_' . $termo_id . '_' . date('Ymd_His') . '.pdf';
$pdf->Output('I', $nome_arquivo);
exit;
