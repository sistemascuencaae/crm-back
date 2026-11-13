<?php

namespace App\Servicios\PaginaWeb;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Limpia el HTML del editor de DOCUMENTOS (CRM, TipTap) antes de guardarlo (ecommerce.documentos.doc_contenido).
 *
 * Es más amplio que LimpiadorHtml (el de las páginas informativas viejas): acepta tipos de letra, tamaños, colores,
 * resaltado, imágenes y tablas. Pero sigue siendo una LISTA BLANCA:
 * - Solo se conservan las etiquetas de PERMITIDAS; las de PROHIBIDAS se borran con todo su contenido; las demás se
 *   quitan dejando su texto.
 * - Se borran TODOS los atributos y se vuelven a poner solo los permitidos, con valores revisados:
 *     · style: se arma de nuevo con lo que se entiende (text-align, color, background-color, font-family de la lista,
 *       font-size en px). El style que llega nunca se copia tal cual.
 *     · <a href>: https://, mailto:, tel: o una ruta de la tienda (/garantia). Los https:// se abren en pestaña nueva.
 *     · <img>: solo imágenes subidas a los documentos (api/public/documentos/imagenes/<40>.<webp|jpg|png>), con alt,
 *       ancho (25/50/75/100 %) y posición (izquierda/centro/derecha) de una lista fija.
 *     · celdas: colspan / rowspan de 2 a 20 y color de fondo.
 * - La dirección de las imágenes se guarda RELATIVA (/api/public/documentos/imagenes/…): el back la completa al
 *   entregarla (DocumentosController::conImagenesCompletas), así sirve en cualquier servidor.
 */
class LimpiadorDocumento
{
    public const RUTA_IMAGENES = '/api/public/documentos/imagenes/';

    // Tipos de letra que se pueden elegir (los mismos de las páginas; la tienda los carga de Google Fonts).
    public const FUENTES = ['Inter', 'Poppins', 'Barlow Condensed', 'Roboto', 'Montserrat', 'Open Sans'];

    private const PERMITIDAS = [
        'p', 'br', 'hr', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li',
        'strong', 'b', 'em', 'i', 'u', 's', 'sub', 'sup', 'span', 'mark', 'a', 'img',
        'table', 'thead', 'tbody', 'tr', 'th', 'td',
    ];

    private const PROHIBIDAS = [
        'script', 'style', 'iframe', 'frame', 'object', 'embed', 'applet', 'form', 'input', 'button',
        'select', 'textarea', 'link', 'meta', 'base', 'svg', 'math', 'template', 'noscript',
        // De las tablas de TipTap: los anchos de columna no se guardan.
        'colgroup', 'col',
    ];

    // Qué estilos acepta cada etiqueta.
    private const ESTILOS = [
        'p'    => ['text-align'],
        'h2'   => ['text-align'],
        'h3'   => ['text-align'],
        'h4'   => ['text-align'],
        'span' => ['color', 'background-color', 'font-family', 'font-size'],
        'mark' => ['color', 'background-color'],
        'td'   => ['text-align', 'background-color'],
        'th'   => ['text-align', 'background-color'],
    ];

    private const ANCHOS_IMAGEN = ['25', '50', '75', '100'];
    private const POSICIONES_IMAGEN = ['izquierda', 'centro', 'derecha'];

    public function limpiar(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $doc = new DOMDocument('1.0', 'UTF-8');
        $erroresPrevios = libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><div>' . $html . '</div>',   // la declaración evita que se dañen las tildes
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

        // Un documento que quedó sin texto ni imágenes ("<p></p>") se guarda vacío.
        $salida = trim($salida);
        return trim(strip_tags($salida, '<img><hr>')) === '' && !str_contains($salida, '<img') ? '' : $salida;
    }

    private function limpiarHijos(DOMNode $nodo): void
    {
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

                // El título del documento lo pone la tienda: un <h1> pasa a subtítulo.
                if ($etiqueta === 'h1') {
                    $hijo = $this->renombrar($nodo, $hijo, 'h2');
                    $etiqueta = 'h2';
                }

                if (!in_array($etiqueta, self::PERMITIDAS, true)) {
                    $this->quitarEtiqueta($nodo, $hijo);
                    continue;
                }

                $resultado = $this->limpiarAtributos($hijo, $etiqueta);
                if ($resultado === 'quitar') {
                    $this->quitarEtiqueta($nodo, $hijo);       // enlace sin dirección válida: queda el texto
                } elseif ($resultado === 'borrar') {
                    $nodo->removeChild($hijo);                 // imagen que no es nuestra: fuera
                } elseif ($etiqueta === 'span' && !$hijo->hasAttributes()) {
                    $this->quitarEtiqueta($nodo, $hijo);       // <span> sin estilo no aporta nada
                }
            } elseif ($hijo->nodeType !== XML_TEXT_NODE) {
                $nodo->removeChild($hijo);   // comentarios, CDATA, instrucciones
            }
        }
    }

    // Borra todos los atributos y vuelve a poner los permitidos. Devuelve 'ok', 'quitar' (dejar el texto) o 'borrar'.
    private function limpiarAtributos(DOMElement $el, string $etiqueta): string
    {
        $leidos = [];
        foreach (['style', 'href', 'src', 'alt', 'colspan', 'rowspan', 'data-ancho', 'data-alinear', 'data-align'] as $a) {
            $leidos[$a] = trim($el->getAttribute($a));
        }

        $nombres = [];
        foreach ($el->attributes as $atributo) {
            $nombres[] = $atributo->nodeName;
        }
        foreach ($nombres as $nombre) {
            $el->removeAttribute($nombre);
        }

        // Estilos permitidos, armados de nuevo.
        if (isset(self::ESTILOS[$etiqueta])) {
            $estilo = $this->estilo($leidos['style'], self::ESTILOS[$etiqueta], $leidos['data-align']);
            if ($estilo !== '') {
                $el->setAttribute('style', $estilo);
            }
        }

        if ($etiqueta === 'td' || $etiqueta === 'th') {
            foreach (['colspan', 'rowspan'] as $a) {
                if (preg_match('/^([2-9]|1[0-9]|20)$/', $leidos[$a])) {
                    $el->setAttribute($a, $leidos[$a]);
                }
            }
        }

        if ($etiqueta === 'a') {
            $href = $leidos['href'];
            // Un PDF de los documentos (con la dirección completa de cualquier servidor) se guarda relativo.
            if (preg_match('#^(?:https?://[^/\s]+)?(/api/public/documentos/pdf/[A-Za-z0-9]{40}\.pdf)$#', $href, $m)) {
                $href = $m[1];
            }
            if (!preg_match('#^(https://\S+|mailto:\S+|tel:[0-9+\-\s()]+|/(?!/)\S*)$#i', $href)) {
                return 'quitar';
            }
            $el->setAttribute('href', $href);
            if (stripos($href, 'https://') === 0) {
                $el->setAttribute('target', '_blank');
                $el->setAttribute('rel', 'noopener noreferrer');
            }
        }

        if ($etiqueta === 'img') {
            $nombre = self::nombreImagen($leidos['src']);
            if ($nombre === null) {
                return 'borrar';
            }
            $el->setAttribute('src', self::RUTA_IMAGENES . $nombre);
            $el->setAttribute('alt', mb_substr($leidos['alt'], 0, 200));
            $ancho = in_array($leidos['data-ancho'], self::ANCHOS_IMAGEN, true) ? $leidos['data-ancho'] : '100';
            $posicion = in_array($leidos['data-alinear'], self::POSICIONES_IMAGEN, true) ? $leidos['data-alinear'] : 'centro';
            $el->setAttribute('data-ancho', $ancho);
            $el->setAttribute('data-alinear', $posicion);
            // Clases para la tienda (Angular quita los atributos data- y style al mostrar el HTML).
            $el->setAttribute('class', 'ae-img ae-img-' . $ancho . ' ae-img-' . $posicion);
        }

        return 'ok';
    }

    // El nombre de una imagen de documentos a partir de su dirección (completa o relativa); null si no es nuestra.
    public static function nombreImagen(string $src): ?string
    {
        return preg_match('#^(?:https?://[^/\s]+)?/api/public/documentos/imagenes/([A-Za-z0-9]{40}\.(?:webp|jpg|png))$#', $src, $m)
            ? $m[1] : null;
    }

    // Arma el style con solo las propiedades permitidas y valores revisados.
    private function estilo(string $estilo, array $permitidas, string $dataAlign): string
    {
        $valores = [];
        foreach (explode(';', $estilo) as $declaracion) {
            if (!str_contains($declaracion, ':')) {
                continue;
            }
            [$prop, $valor] = array_map('trim', explode(':', $declaracion, 2));
            $prop = strtolower($prop);
            if (!in_array($prop, $permitidas, true)) {
                continue;
            }
            $limpio = match ($prop) {
                'text-align' => preg_match('/^(left|center|right|justify)$/i', $valor) ? strtolower($valor) : null,
                'color', 'background-color' => $this->color($valor),
                'font-family' => $this->fuente($valor),
                'font-size' => preg_match('/^(\d{1,2})(?:\.\d+)?px$/i', $valor, $m) && $m[1] >= 8 && $m[1] <= 72 ? $m[1] . 'px' : null,
                default => null,
            };
            if ($limpio !== null) {
                $valores[$prop] = $limpio;
            }
        }
        if (!isset($valores['text-align']) && in_array('text-align', $permitidas, true)
            && preg_match('/^(left|center|right|justify)$/i', $dataAlign)) {
            $valores['text-align'] = strtolower($dataAlign);
        }
        // Alineación normal (izquierda): no hace falta guardarla.
        if (($valores['text-align'] ?? null) === 'left') {
            unset($valores['text-align']);
        }

        $partes = [];
        foreach ($valores as $p => $v) {
            $partes[] = $p . ': ' . $v;
        }
        return implode('; ', $partes);
    }

    // #abc, #aabbcc o rgb(a) con números: devuelve el color en hexadecimal; null si no se entiende.
    private function color(string $valor): ?string
    {
        $valor = strtolower(trim($valor));
        if (preg_match('/^#([0-9a-f]{3}|[0-9a-f]{6})$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/', $valor, $m)
            && $m[1] <= 255 && $m[2] <= 255 && $m[3] <= 255) {
            return sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
        }
        return null;
    }

    // Solo los tipos de letra de la lista (el primero que se reconozca, con su respaldo).
    private function fuente(string $valor): ?string
    {
        foreach (explode(',', $valor) as $nombre) {
            $nombre = trim($nombre, " \t\"'");
            foreach (self::FUENTES as $f) {
                if (strcasecmp($nombre, $f) === 0) {
                    return "'" . $f . "', sans-serif";
                }
            }
        }
        return null;
    }

    private function renombrar(DOMNode $padre, DOMElement $elemento, string $nueva): DOMElement
    {
        $reemplazo = $elemento->ownerDocument->createElement($nueva);
        while ($elemento->firstChild) {
            $reemplazo->appendChild($elemento->firstChild);
        }
        $padre->replaceChild($reemplazo, $elemento);
        return $reemplazo;
    }

    private function quitarEtiqueta(DOMNode $padre, DOMElement $elemento): void
    {
        while ($elemento->firstChild) {
            $padre->insertBefore($elemento->firstChild, $elemento);
        }
        $padre->removeChild($elemento);
    }
}
