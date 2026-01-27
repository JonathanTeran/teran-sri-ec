<?php

declare(strict_types=1);

namespace Teran\Sri\Generators;

use DOMDocument;
use DOMElement;
use Teran\Sri\Utils\ClaveAcceso;

abstract class XmlGenerator
{
    protected DOMDocument $dom;

    public function __construct()
    {
        $this->dom = new DOMDocument('1.0', 'UTF-8');
        $this->dom->preserveWhiteSpace = false;
        $this->dom->formatOutput = true;
    }

    /**
     * Genera el nodo infoTributaria compartido.
     */
    protected function createInfoTributaria(DOMElement $root, array $data): void
    {
        $infoTributaria = $this->dom->createElement('infoTributaria');
        $root->appendChild($infoTributaria);

        $fields = [
            'ambiente' => $data['ambiente'],
            'tipoEmision' => $data['tipoEmision'] ?? '1',
            'razonSocial' => $data['razonSocial'],
            'nombreComercial' => $data['nombreComercial'] ?? null,
            'ruc' => $data['ruc'],
            'claveAcceso' => $data['claveAcceso'],
            'codDoc' => $data['codDoc'],
            'estab' => $data['estab'],
            'ptoEmi' => $data['ptoEmi'],
            'secuencial' => $data['secuencial'],
            'dirMatriz' => $data['dirMatriz'],
            'agenteRetencion' => $data['agenteRetencion'] ?? null,
            'contribuyenteRimpe' => $data['contribuyenteRimpe'] ?? null,
        ];

        foreach ($fields as $key => $value) {
            if ($value !== null) {
                $infoTributaria->appendChild($this->dom->createElement($key, (string)$value));
            }
        }
    }

    protected function addInfoAdicional(DOMElement $root, ?array $infoAdicional): void
    {
        if (empty($infoAdicional)) {
            return;
        }

        $node = $this->dom->createElement('infoAdicional');
        $root->appendChild($node);

        foreach ($infoAdicional as $nombre => $valor) {
            $campo = $this->dom->createElement('campoAdicional', (string)$valor);
            $campo->setAttribute('nombre', (string)$nombre);
            $node->appendChild($campo);
        }
    }

    /**
     * Formatea valores numéricos según requerimiento SRI.
     */
    protected function formatValue(string|int|float $value, int $decimals = 2): string
    {
        return number_format((float)$value, $decimals, '.', '');
    }

    abstract public function generate(array $data): string;
}
