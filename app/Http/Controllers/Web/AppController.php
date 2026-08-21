<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Execution;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class AppController extends Controller
{
    public function index(): View
    {
        return view('layouts.app');
    }

    /**
     * Tela de criação de Briefings.
     *
     * A Brand pode vir na query string; a ausência é resolvida pelo próprio
     * componente, que assume a Brand ativa do usuário.
     */
    public function briefing(Request $request): View
    {
        $projectId = $request->query('project_id');

        return view('pages.briefing', [
            'projectId' => is_numeric($projectId) ? (int) $projectId : null,
        ]);
    }

    /**
     * Tela de acompanhamento e resultado de uma Execution.
     *
     * A autorização é da ExecutionPolicy. A Brand ativa não recorta esta tela:
     * uma Execution de outra Brand do mesmo usuário continua sendo dele e
     * precisa abrir por link direto (ver ExecutionPolicy).
     */
    public function result(Execution $execution): View
    {
        $this->authorize('view', $execution);

        return view('pages.result', [
            'execution' => $execution,
        ]);
    }
}
