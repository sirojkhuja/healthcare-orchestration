<?php

namespace App\Modules\Treatment\Presentation\Http\Controllers;

use App\Modules\Treatment\Application\Commands\ApproveTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\FinishTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\PauseTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\RejectTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\ResumeTreatmentPlanCommand;
use App\Modules\Treatment\Application\Commands\StartTreatmentPlanCommand;
use App\Modules\Treatment\Application\Handlers\ApproveTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\FinishTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\PauseTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\RejectTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\ResumeTreatmentPlanCommandHandler;
use App\Modules\Treatment\Application\Handlers\StartTreatmentPlanCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TreatmentPlanWorkflowController
{
    public function approve(string $planId, ApproveTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $plan = $handler->handle(new ApproveTreatmentPlanCommand($planId));

        return response()->json([
            'status' => 'treatment_plan_approved',
            'data' => $plan->toArray(),
        ]);
    }

    public function finish(string $planId, FinishTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $plan = $handler->handle(new FinishTreatmentPlanCommand($planId));

        return response()->json([
            'status' => 'treatment_plan_finished',
            'data' => $plan->toArray(),
        ]);
    }

    public function pause(string $planId, Request $request, PauseTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $plan = $handler->handle(new PauseTreatmentPlanCommand($planId, trim($validated['reason'])));

        return response()->json([
            'status' => 'treatment_plan_paused',
            'data' => $plan->toArray(),
        ]);
    }

    public function reject(string $planId, Request $request, RejectTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string} $validated */
        $plan = $handler->handle(new RejectTreatmentPlanCommand($planId, trim($validated['reason'])));

        return response()->json([
            'status' => 'treatment_plan_rejected',
            'data' => $plan->toArray(),
        ]);
    }

    public function resume(string $planId, ResumeTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $plan = $handler->handle(new ResumeTreatmentPlanCommand($planId));

        return response()->json([
            'status' => 'treatment_plan_resumed',
            'data' => $plan->toArray(),
        ]);
    }

    public function start(string $planId, StartTreatmentPlanCommandHandler $handler): JsonResponse
    {
        $plan = $handler->handle(new StartTreatmentPlanCommand($planId));

        return response()->json([
            'status' => 'treatment_plan_started',
            'data' => $plan->toArray(),
        ]);
    }
}
