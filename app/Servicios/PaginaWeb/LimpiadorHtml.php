<?php

namespace App\Servicios\PaginaWeb;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpia el HTML que llega del editor del CRM antes de guardarlo.
 *
 * - Conserva solo etiquetas de texto (párrafos, subtítulos, negrita, listas, enlaces, tablas…), sin atributos.
 * - Excepciones: el href seguro de los enlaces, colspan/rowspan de las celdas, y la alineación.
 *   La alineación llega como style="text-align: center" (o data-align="center") y se guarda así:
 *       class="ae-alinear-centro" style="text-align: center"
 *   La clase es para la tienda (Angular quita los style al mostrar el HTML) y el style es para
 *   que el editor del CRM la reconozca al volver a abrir el documento. Ese style lo escribe
 *   el limpiador con uno de 3 valores fijos: el style que llega nunca se copia.
 * - Borra con todo su contenido las peligrosas (script, iframe, style, form…).
 * - Las demás (span, div, font, h1…) se quitan pero su texto se conserva.
 * - En los enlaces solo deja href seguro: https://, mailto:, tel: o una ruta de la tienda (/garantia).
 *   Los https:// se abren en pestaña nueva.
 *
 * Usa DOMDocument, que ya viene con PHP: no hace falta instalar paquetes.
 */
class LimpiadorHtml
{
    // Se conservan (sin atributos, salvo el href de <a>)
    private const PERMITIDAS = [
        'p', 'br', 'hr', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'a', 'blockquote',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    // Etiquetas que pueden ir alineadas, y la clase que se guarda para cada alineación
    // (izquierda es la normal: no lleva clase)
    private const CON_ALINEACION = ['p', 'h2', 'h3', 'h4'];
    private const CLASES_ALINEACION = [
        'center'  => 'ae-alinear-centro',
        'right'   => 'ae-alinear-derecha',
        'justify' => 'ae-alinear-justificado',
    ];

    // Se borran con todo su contenido
    private const PROHIBIDAS = [
        'script', 'style', 'iframe', 'frame', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
    ];

    public function limpiar(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $erroresPrevios = libxml_use_internal_errors(true);   // HTML del editor: no detener por avisos
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',  // la declaración evita que se dañen las tildes
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($erroresPrevios);

        $raiz = $doc->getElementsByTagName('div')->item(0);
        if ($raiz === null) {
            return '';
        }

        $this->limpiarHijos($raiz);

        $salida = '';
        foreach ($raiz->childNodes as $hijo) {
            $salida .= $doc->saveHTML($hijo);
        }

        return trim($salida);
    }

    private function limpiarHijos(DOMNode $nodo): void
    {
        // Copia de la lista, porque se modifica mientras se recorre
        $hijos = [];
        foreach ($nodo->childNodes as $hijo) {
            $hijos[] = $hijo;
        }

        foreach ($hijos as $hijo) {
            if ($hijo instanceof DOMElement) {
                $etiqueta = strtolower($hijo->nodeName);

                if (in_array($etiqueta, self::PROHIBIDAS, true)) {
                    $nodo->removeChild($hijo);
                    continue;
                }

                $this->limpiarHijos($hijo);

                // El título grande de la página lo pone el diseño: un <h1> pegado pasa a subtítulo
                if ($etiqueta === 'h1') {
                    $hijo = $this->renombrar($nodo, $hijo, 'h2');
                    $etiqueta = 'h2';
                }

                if (!in_array($etiqueta, self::PERMITIDAS, true) || !$this->limpiarAtributos($hijo, $etiqueta)) {
                    $this->quitarEtiqueta($nodo, $hijo);
                }
            } elseif ($hijo->nodeType !== XML_TEXT_NODE) {
                $nodo->removeChild($hijo);   // comentarios, CDATA, instrucciones
            }
        }
    }

    // Quita todos los atributos. Vuelve a poner solo los permitidos: href seguro en <a>,
    // colspan/rowspan en celdas y la clase de alineación. false si un enlace no tiene href seguro.
    private function limpiarAtributos(DOMElement $elemento, string $etiqueta): bool
    {
        // Lo que se conserva se lee antes de borrar todo
        $href    = $etiqueta === 'a' ? trim($elemento->getAttribute('href')) : '';
        $alineacion = in_array($etiqueta, self::CON_ALINEACION, true)
            ? $this->alineacion($elemento->getAttribute('style'), $elemento->getAttribute('data-align'))
            : null;
        $colspan = $elemento->getAttribute('colspan');
        $rowspan = $elemento->getAttribute('rowspan');

        $nombres = [];
        foreach ($elemento->attributes as $atributo) {
            $nombres[] = $atributo->nodeName;
        }
        foreach ($nombres as $nombre) {
            $elemento->removeAttribute($nombre);
        }

        if ($alineacion !== null) {
            $elemento->setAttribute('class', self::CLASES_ALINEACION[$alineacion]);
            $elemento->setAttribute('style', 'text-align: ' . $alineacion);
        }

        // Celdas combinadas: solo números de 2 a 20
        if ($etiqueta === 'td' || $etiqueta === 'th') {
            if (preg_match('/^([2-9]|1[0-9]|20)$/', $colspan)) {
                $elemento->setAttribute('colspan', $colspan);
            }
            if (preg_match('/^([2-9]|1[0-9]|20)$/', $rowspan)) {
                $elemento->setAttribute('rowspan', $rowspan);
            }
        }

        if ($etiqueta !== 'a') {
            return true;
        }

        if (!preg_match('#^(https://\S+|mailto:\S+|tel:[0-9+\-\s()]+|/(?!/)\S*)$#i', $href)) {
            return false;   // sin enlace válido: queda solo el texto
        }

        $elemento->setAttribute('href', $href);
        if (stripos($href, 'https://') === 0) {
            $elemento->setAttribute('target', '_blank');
            $elemento->setAttribute('rel', 'noopener noreferrer');
        }

        return true;
    }

    // Lee la alineación de style="text-align: …" o de data-align="…".
    // Devuelve center, right o justify; null si no hay o es la normal (izquierda).
    private function alineacion(string $estilo, string $dataAlign): ?string
    {
        if (preg_match('/text-align\s*:\s*(center|right|justify)\b/i', $estilo, $m)) {
            return strtolower($m[1]);
        }
        if (preg_match('/^(center|right|justify)$/i', trim($dataAlign), $m)) {
            return strtolower($m[1]);
        }

        return null;
    }

    // Cambia la etiqueta conservando su contenido
    private function renombrar(DOMNode $padre, DOMElement $elemento, string $nueva): DOMElement
    {
        $reemplazo = $elemento->ownerDocument->createElement($nueva);
        while ($elemento->firstChild) {
            $reemplazo->appendChild($elemento->firstChild);
        }
        $padre->replaceChild($reemplazo, $elemento);

        return $reemplazo;
    }

    // Reemplaza la etiqueta por su contenido
    private function quitarEtiqueta(DOMNode $padre, DOMElement $elemento): void
    {
        while ($elemento->firstChild) {
            $padre->insertBefore($elemento->firstChild, $elemento);
        }
        $padre->removeChild($elemento);
    }
}