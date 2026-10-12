<?php

namespace App\Http\Controllers\paginaWeb;

use Illuminate\Http\Request;

// Columnas de enlaces del footer: Contacto, Atención al cliente, Nosotros…
class MenuGrupoController extends PaginaWebController
{
    // POST pagina-web/secciones/{secId}/menu-grupos
    public function store(Request $request, int $secId)
    {
        return $this->responder($this->paginaWeb->crearMenuGrupo([
            'sec_id'     => $secId,
            'mgr_titulo' => $request->input('mgr_titulo'),
            'mgr_activo' => $request->boolean('mgr_activo', true),
            'usu_id'     => $this->usuarioId(),
        ]), 201);
    }

    // PUT pagina-web/menu-grupos/{mgrId}
    public function update(Request $request, int $mgrId)
    {
        return $this->responder($this->paginaWeb->modificarMenuGrupo($mgrId, [
            'mgr_titulo' => $request->input('mgr_titulo'),
            'mgr_activo' => $request->boolean('mgr_activo', true),
            'usu_id'     => $this->usuarioId(),
        ]));
    }

    // DELETE pagina-web/menu-grupos/{mgrId}   (también elimina sus enlaces)
    public function destroy(int $mgrId)
    {
        return $this->responder($this->paginaWeb->eliminarMenuGrupo($mgrId));
    }

    // PUT pagina-web/secciones/{secId}/menu-grupos/orden   body: { "mgr_ids": [3, 1, 2] }
    public function ordenar(Request $request, int $secId)
    {
        return $this->responder($this->paginaWeb->ordenarMenuGrupos([
            'sec_id'  => $secId,
            'mgr_ids' => $this->listaDeIds($request, 'mgr_ids'),
            'usu_id'  => $this->usuarioId(),
        ]));
    }
}
