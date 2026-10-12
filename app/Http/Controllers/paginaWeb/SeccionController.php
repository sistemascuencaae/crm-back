<?php

namespace App\Http\Controllers\paginaWeb;

use Illuminate\Http\Request;

class SeccionController extends PaginaWebController
{
    // GET pagina-web/secciones/{clave}
    public function show(string $clave)
    {
        return $this->responder($this->paginaWeb->buscarSeccion($clave));
    }

    // PUT pagina-web/secciones/{secId}
    public function update(Request $request, int $secId)
    {
        return $this->responder($this->paginaWeb->modificarSeccion($secId, [
            'sec_activo' => $request->boolean('sec_activo'),
            'sec_config' => $request->input('sec_config', []),
            'usu_id'     => $this->usuarioId(),
        ]));
    }

    // POST pagina-web/secciones/{secId}/menu-items   (en el footer, con mgr_id)
    public function crearMenuItem(Request $request, int $secId)
    {
        return $this->responder($this->paginaWeb->crearMenuItem([
            'sec_id'     => $secId,
            'mgr_id'     => $request->input('mgr_id'),
            'mit_texto'  => $request->input('mit_texto'),
            'mit_enlace' => $request->input('mit_enlace'),
            'mit_icono'  => $request->input('mit_icono'),
            'mit_activo' => $request->boolean('mit_activo', true),
            'usu_id'     => $this->usuarioId(),
        ]), 201);
    }

    // PUT pagina-web/menu-items/{mitId}
    public function modificarMenuItem(Request $request, int $mitId)
    {
        return $this->responder($this->paginaWeb->modificarMenuItem($mitId, [
            'mit_texto'  => $request->input('mit_texto'),
            'mit_enlace' => $request->input('mit_enlace'),
            'mit_icono'  => $request->input('mit_icono'),
            'mit_activo' => $request->boolean('mit_activo', true),
            'usu_id'     => $this->usuarioId(),
        ]));
    }

    // DELETE pagina-web/menu-items/{mitId}
    public function eliminarMenuItem(int $mitId)
    {
        return $this->responder($this->paginaWeb->eliminarMenuItem($mitId));
    }

    // PUT pagina-web/secciones/{secId}/menu-items/orden
    // body: { "mit_ids": [3, 1, 2] }  (en el footer también "mgr_id": 5)
    public function ordenarMenuItems(Request $request, int $secId)
    {
        return $this->responder($this->paginaWeb->ordenarMenuItems([
            'sec_id'  => $secId,
            'mgr_id'  => $request->input('mgr_id'),
            'mit_ids' => $this->listaDeIds($request, 'mit_ids'),
            'usu_id'  => $this->usuarioId(),
        ]));
    }
}
