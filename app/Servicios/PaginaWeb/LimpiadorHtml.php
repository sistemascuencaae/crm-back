<?php

namespace App\Servicios\PaginaWeb;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpia el HTML que llega del editor del CRM antes de guardarlo.
 */
class LimpiadorHtml
{
    // Se conservan (sin atributos, salvo el href de <a>)
    private const PERMITIDAS = [
        'p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's',
        'ul', 'ol', 'li', 'a', 'blockquote',
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

    // Quita todos los atributos. En <a> deja solo un href seguro; false si el enlace no tiene uno.
    private function limpiarAtributos(DOMElement $elemento, string $etiqueta): bool
    {
        $href = $etiqueta === 'a' ? trim($elemento->getAttribute('href')) : '';

        $nombres = [];
        foreach ($elemento->attributes as $atributo) {
            $nombres[] = $atributo->nodeName;
        }
        foreach ($nombres as $nombre) {
            $elemento->removeAttribute($nombre);
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