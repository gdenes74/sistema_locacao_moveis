<?php
/** Medidas em cm; corte por fileiras independentes de cada tipo de peça. */
class PlanoCorteTecido
{
    /**
     * Identifica um desenho técnico aprovado associado à ficha. A chave fica
     * registrada na própria ficha técnica do produto/serviço, para que o motor
     * continue extensível sem criar uma tela exclusiva por produto.
     */
    public static function planoVisualTecnico(array $item): ?array
    {
        $texto = self::instrucoes($item);
        // A chave histórica continua sendo reconhecida como metadado, mas o
        // plano agora é desenhado pelo resultado da calculadora, nunca por PNG.
        if (stripos($texto, '[PLANO_VISUAL:PUFE_REDONDO_120]') !== false)
            return ['chave'=>'PUFE_REDONDO_120','titulo'=>'Plano técnico de corte','descricao'=>'Gerado a partir das peças e da quantidade desta ficha.'];
        if (self::sugerirPecas($texto, 150))
            return ['chave'=>'GENERICO','titulo'=>'Plano técnico de corte','descricao'=>'Gerado a partir das referências técnicas cadastradas.'];
        return null;
    }

    public static function instrucoes(array $item): string
    {
        $partes=[];
        foreach (['componente_descricao_detalhada','componente_observacoes','referencia_corte_texto','produto_descricao_detalhada','produto_observacoes','observacoes'] as $campo) {
            foreach (preg_split('/\R\s*\R/u', trim((string)($item[$campo]??''))) as $s) {
                $s=trim($s);
                if ($s!=='' && !in_array($s,$partes,true)) $partes[]=$s;
            }
        }
        return implode("\n\n",$partes);
    }
    public static function sugerirPecas(string $texto, float $larguraUtil = 152): array
    {
        // Reconhece corte explícito; dimensões da estrutura nunca viram molde.
        if (preg_match('/tampo\s+(?:de\s+corte\s*:?\s*)?37[,.]5\s*[x×]\s*37[,.]5\s*cm/iu',$texto)
            && preg_match('/(?:faixa|saia)[^.;\n]{0,35}50\s*[x×]\s*150\s*cm/iu',$texto)) {
            return [
                ['nome'=>'Tampo: corte 37,5 × 37,5; reserva de planejamento 40 × 40','largura'=>40,'comprimento'=>40,'por_unidade'=>1],
                ['nome'=>'Saia/faixa: folgas já incluídas','largura'=>150,'comprimento'=>50,'por_unidade'=>1],
            ];
        }
        $numero=static fn($v)=>(float)str_replace(',','.',(string)$v);
        // Plano com reaproveitamento explícito: o tampo está dentro da sobra da saia.
        if(preg_match('/saia\s+inteira\s*(?:de\s*)?(\d+(?:[,.]\d+)?)\s*[x×]\s*(\d+(?:[,.]\d+)?)\s*m/iu',$texto,$saia)
            && preg_match('/tampo\s*(?:de\s*)?(\d+(?:[,.]\d+)?)\s*[x×]\s*(\d+(?:[,.]\d+)?)\s*m/iu',$texto,$tampo)
            && preg_match('/(?:tampo[^.]*sobra|sobra[^.]*tampo)/iu',$texto)) {
            return [
                ['nome'=>'Saia inteira, sem emenda','largura'=>$numero($saia[2])*100,'comprimento'=>$numero($saia[1])*100,'por_unidade'=>1,'por_fileira_max'=>1],
                ['nome'=>'Tampo retirado da sobra da saia','largura'=>$numero($tampo[2])*100,'comprimento'=>$numero($tampo[1])*100,'por_unidade'=>1,'sobra_de'=>0],
            ];
        }
        $pecas=[];
        // Comprimento definido x largura do tecido (capa da estrutura sem braços).
        if(preg_match('/(\d+)\s*panos?\s*de\s*(\d+(?:[,.]\d+)?)\s*m\s*[x×]\s*largura\s+do\s+tecido/iu',$texto,$panos)) {
            $pecas[]=['nome'=>'Panos inteiros — união com costura central','largura'=>$larguraUtil,'comprimento'=>$numero($panos[2])*100,'por_unidade'=>(int)$panos[1]];
        }
        if(preg_match_all('/(\d+)\s*peças?\s*(?:de\s*)?(?:aproximadamente\s*)?(\d+(?:[,.]\d+)?)\s*[x×]\s*(\d+(?:[,.]\d+)?)\s*(cm|m)\b(?:\s+para\s+(?:o|a)\s+([^.;\n]{2,40}))?/iu',$texto,$matches,PREG_SET_ORDER)) {
            foreach($matches as $m) {
                $f=strtolower($m[4])==='m'?100:1;
                $nome=trim((string)($m[5]??''));
                $p=['nome'=>$nome!==''?ucfirst($nome):'Peça de corte cadastrada','largura'=>(float)str_replace(',','.',$m[2])*$f,'comprimento'=>(float)str_replace(',','.',$m[3])*$f,'por_unidade'=>(int)$m[1]];
                if(!in_array($p,$pecas,true)) $pecas[]=$p;
            }
        }
        // Corte retangular único: não confundir medida pronta de três dimensões.
        if(!$pecas && preg_match_all('/(?:medida\s+de\s+corte(?:\s+do\s+tecido)?|corte\s+da\s+capa|corte)\s*:\s*(\d+(?:[,.]\d+)?)\s*[x×]\s*(\d+(?:[,.]\d+)?)\s*(cm|m)\b/iu',$texto,$matches,PREG_SET_ORDER)) {
            foreach($matches as $m) {
                $f=strtolower($m[3])==='m'?100:1;
                $a=$numero($m[1])*$f;$b=$numero($m[2])*$f;
                $p=['nome'=>'Peça de corte cadastrada','largura'=>min($a,$b),'comprimento'=>max($a,$b),'por_unidade'=>1];
                if(!in_array($p,$pecas,true))$pecas[]=$p;
            }
        }
        // Alguns serviços de forração têm consumo técnico validado, mas ainda
        // não possuem a decomposição em peças. Ele já permite planejar a compra
        // por pedido/período sem inventar moldes; as peças detalhadas podem ser
        // cadastradas depois e passam a ter prioridade sobre esta referência.
        if(!$pecas && preg_match('/consumo\s+(?:t[eé]cnico|de\s+refer[eê]ncia)(?:\s+para\s+produ[cç][aã]o\s+individual)?\s*:\s*(?:aproximadamente\s+)?(\d+(?:[,.]\d+)?)\s*(?:m|metros?)\s+lineares?/iu',$texto,$consumo)) {
            $pecas[]=['nome'=>'Consumo linear de referência — sem molde detalhado',
                'largura'=>$larguraUtil,'comprimento'=>$numero($consumo[1])*100,'por_unidade'=>1,'por_fileira_max'=>1];
        }
        return $pecas;
    }
    public static function calcular(int $quantidade,array $pecas,float $largura,float $borda): array
    {
        if($quantidade<1||$quantidade>10000||!is_finite($largura)||!is_finite($borda)||$borda<0||$largura<=2*$borda)
            throw new InvalidArgumentException('Informe quantidade positiva e largura maior que as duas bordas.');
        if(!$pecas||count($pecas)>30) throw new InvalidArgumentException('Informe entre uma e 30 peças de corte.');
        $util=$largura-2*$borda; $linhas=[]; $total=0; $area=0;
        // Namoradeira: saias lado a lado e tampos em faixa separada. O
        // cálculo antigo repetia 4,65 m por unidade e não aproveitava o rolo.
        if(self::ehPacoteNamoradeira($pecas)) {
            return self::calcularNamoradeira($quantidade,$pecas,$util);
        }
        foreach($pecas as $indice=>$p) {
            $w=(float)($p['largura']??0);$h=(float)($p['comprimento']??0);$n=(float)($p['por_unidade']??0);
            if(!is_finite($w)||!is_finite($h)||!is_finite($n)||$w<=0||$h<=0||$n<1||floor($n)!=$n)
                throw new InvalidArgumentException('As peças precisam de medidas positivas e quantidade inteira.');
            if(isset($p['sobra_de']) && (string)$p['sobra_de']!=='') {
                $pai=(int)$p['sobra_de'];
                if(!isset($linhas[$pai])||$pai>=$indice||!empty($linhas[$pai]['reuso']))throw new InvalidArgumentException('Referência da sobra inválida.');
                $base=$linhas[$pai];
                $sobra=$util-(float)$base['largura']*(int)$base['por_fileira'];
                $porLargura=(int)floor(($sobra+0.000001)/$w);$porComprimento=(int)floor(((float)$base['comprimento']+0.000001)/$h);
                $capacidade=$porLargura*$porComprimento*(int)$base['fileiras'];
                if($quantidade*$n>$capacidade)throw new InvalidArgumentException('O tampo não cabe na sobra lateral da saia com a largura informada. Confira as medidas do tecido.');
                $linhas[]=array_merge($p,['total_pecas'=>$quantidade*(int)$n,'por_fileira'=>$porLargura,'fileiras'=>0,'metros'=>0,'reuso'=>true,'base'=>$base,'sobra_largura'=>$sobra]);
                $area+=$quantidade*$n*$w*$h/10000;
                continue;
            }
            $porFileira=(int)floor(($util+0.000001)/$w);
            if(!empty($p['por_fileira_max']))$porFileira=min($porFileira,(int)$p['por_fileira_max']);
            if($porFileira<1) throw new InvalidArgumentException('A peça “'.$p['nome'].'” não cabe na largura útil. Ajuste a orientação ou a largura do tecido.');
            $necessarias=$quantidade*(int)$n; $fileiras=(int)ceil($necessarias/$porFileira);$metros=$fileiras*$h/100;
            $linhas[]=array_merge($p,['total_pecas'=>$necessarias,'por_fileira'=>$porFileira,'fileiras'=>$fileiras,'metros'=>$metros]);
            $total+=$metros;$area+=$necessarias*$w*$h/10000;
        }
        return ['largura_util'=>$util,'linhas'=>$linhas,'metros'=>$total,'area_pecas'=>$area];
    }

    private static function ehPacoteNamoradeira(array $pecas): bool
    {
        if(count($pecas)!==2) return false;
        $saia=$pecas[0]; $tampo=$pecas[1];
        return isset($tampo['sobra_de'])
            && (int)$tampo['sobra_de']===0
            && (float)($saia['largura']??0)>=50 && (float)($saia['largura']??0)<=60
            && (float)($saia['comprimento']??0)>=400
            && (float)($tampo['largura']??0)>=65 && (float)($tampo['largura']??0)<=80
            && (float)($tampo['comprimento']??0)>=150;
    }

    private static function calcularNamoradeira(int $quantidade,array $pecas,float $util): array
    {
        $saia=$pecas[0]; $tampo=$pecas[1];
        $nSaia=max(1,(int)($saia['por_unidade']??1));
        $nTampo=max(1,(int)($tampo['por_unidade']??1));
        if($nSaia!==$nTampo) throw new InvalidArgumentException('As peças da namoradeira precisam ter a mesma quantidade por unidade.');
        $q=$quantidade*$nSaia;
        $wSaia=(float)$saia['largura']; $hSaia=(float)$saia['comprimento'];
        // A medida útil informada (72 x 162) recebe a folga prática de corte
        // solicitada: 75 x 165 cm.
        $wTampo=max(5.0,ceil((float)$tampo['largura']/5)*5);
        $hTampo=max(5.0,ceil((float)$tampo['comprimento']/5)*5);
        if($q===1) {
            $sobra=max(0,$util-$wSaia);
            $porSobra=max(0,(int)floor(($sobra+0.000001)/$wTampo));
            $linhaSaia=array_merge($saia,[
                'total_pecas'=>1,'por_fileira'=>1,'fileiras'=>1,
                'metros'=>$hSaia/100,'por_unidade'=>$nSaia,
                'nota_planejamento'=>'Saia inteira; o tampo é retirado da sobra lateral.'
            ]);
            $linhaTampo=array_merge($tampo,[
                'largura'=>$wTampo,'comprimento'=>$hTampo,'total_pecas'=>1,
                'por_fileira'=>$porSobra,'fileiras'=>0,'metros'=>0,
                'por_unidade'=>$nTampo,'reuso'=>true,'base'=>$linhaSaia,
                'sobra_largura'=>$sobra,
                'nota_planejamento'=>'Tampo aproveitado na sobra lateral da saia.'
            ]);
            return [
                'largura_util'=>$util,
                'linhas'=>[$linhaSaia,$linhaTampo],
                'metros'=>$hSaia/100,
                'area_pecas'=>$wSaia*$hSaia/10000 + $wTampo*$hTampo/10000,
            ];
        }
        $porSaia=max(1,(int)floor(($util+0.000001)/$wSaia));
        $porTampo=max(1,(int)floor(($util+0.000001)/$wTampo));
        $fileirasSaia=(int)ceil($q/$porSaia);
        $fileirasTampo=(int)ceil($q/$porTampo);
        $metrosSaia=$fileirasSaia*$hSaia/100;
        $metrosTampo=$fileirasTampo*$hTampo/100;
        $linhaSaia=array_merge($saia,[
            'total_pecas'=>$q,'por_fileira'=>$porSaia,'fileiras'=>$fileirasSaia,
            'metros'=>$metrosSaia,'por_unidade'=>$nSaia,
            'nota_planejamento'=>'Saias agrupadas na largura útil; corte longitudinal.'
        ]);
        $linhaTampo=array_merge($tampo,[
            'largura'=>$wTampo,'comprimento'=>$hTampo,'total_pecas'=>$q,
            'por_fileira'=>$porTampo,'fileiras'=>$fileirasTampo,
            'metros'=>$metrosTampo,'por_unidade'=>$nTampo,'reuso'=>false,
            'nota_planejamento'=>'Tampos cortados em faixa separada, aproveitando duas peças por largura quando possível.'
        ]);
        return [
            'largura_util'=>$util,
            'linhas'=>[$linhaSaia,$linhaTampo],
            'metros'=>$metrosSaia+$metrosTampo,
            'area_pecas'=>$q*$wSaia*$hSaia/10000 + $q*$wTampo*$hTampo/10000,
        ];
    }
}
