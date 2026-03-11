<?php

namespace App\Modules\Insurance\Presentation\Http\Controllers;

use App\Modules\Insurance\Application\Commands\ApproveClaimCommand;
use App\Modules\Insurance\Application\Commands\DenyClaimCommand;
use App\Modules\Insurance\Application\Commands\MarkClaimPaidCommand;
use App\Modules\Insurance\Application\Commands\ReopenClaimCommand;
use App\Modules\Insurance\Application\Commands\StartClaimReviewCommand;
use App\Modules\Insurance\Application\Commands\SubmitClaimCommand;
use App\Modules\Insurance\Application\Handlers\ApproveClaimCommandHandler;
use App\Modules\Insurance\Application\Handlers\DenyClaimCommandHandler;
use App\Modules\Insurance\Application\Handlers\MarkClaimPaidCommandHandler;
use App\Modules\Insurance\Application\Handlers\ReopenClaimCommandHandler;
use App\Modules\Insurance\Application\Handlers\StartClaimReviewCommandHandler;
use App\Modules\Insurance\Application\Handlers\SubmitClaimCommandHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ClaimWorkflowController
{
    public function approve(string $claimId, Request $request, ApproveClaimCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'approved_amount' => ['required', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:5000'],
            'source_evidence' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{approved_amount: string, reason: string, source_evidence: string} $validated */
        $claim = $handler->handle(new ApproveClaimCommand(
            claimId: $claimId,
            approvedAmount: $validated['approved_amount'],
            reason: trim($validated['reason']),
            sourceEvidence: trim($validated['source_evidence']),
        ));

        return response()->json([
            'status' => 'claim_approved',
            'data' => $claim->toArray(),
        ]);
    }

    public function deny(string $claimId, Request $request, DenyClaimCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'source_evidence' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string, source_evidence: string} $validated */
        $claim = $handler->handle(new DenyClaimCommand($claimId, trim($validated['reason']), trim($validated['source_evidence'])));

        return response()->json([
            'status' => 'claim_denied',
            'data' => $claim->toArray(),
        ]);
    }

    public function markPaid(string $claimId, Request $request, MarkClaimPaidCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'paid_amount' => ['required', 'regex:/^\\d{1,10}(\\.\\d{1,2})?$/'],
            'reason' => ['required', 'string', 'max:5000'],
            'source_evidence' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{paid_amount: string, reason: string, source_evidence: string} $validated */
        $claim = $handler->handle(new MarkClaimPaidCommand(
            claimId: $claimId,
            paidAmount: $validated['paid_amount'],
            reason: trim($validated['reason']),
            sourceEvidence: trim($validated['source_evidence']),
        ));

        return response()->json([
            'status' => 'claim_paid',
            'data' => $claim->toArray(),
        ]);
    }

    public function reopen(string $claimId, Request $request, ReopenClaimCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'source_evidence' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string, source_evidence: string} $validated */
        $claim = $handler->handle(new ReopenClaimCommand($claimId, trim($validated['reason']), trim($validated['source_evidence'])));

        return response()->json([
            'status' => 'claim_reopened',
            'data' => $claim->toArray(),
        ]);
    }

    public function startReview(string $claimId, Request $request, StartClaimReviewCommandHandler $handler): JsonResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:5000'],
            'source_evidence' => ['required', 'string', 'max:5000'],
        ]);
        /** @var array{reason: string, source_evidence: string} $validated */
        $claim = $handler->handle(new StartClaimReviewCommand($claimId, trim($validated['reason']), trim($validated['source_evidence'])));

        return response()->json([
            'status' => 'claim_review_started',
            'data' => $claim->toArray(),
        ]);
    }

    public function submit(string $claimId, SubmitClaimCommandHandler $handler): JsonResponse
    {
        $claim = $handler->handle(new SubmitClaimCommand($claimId));

        return response()->json([
            'status' => 'claim_submitted',
            'data' => $claim->toArray(),
        ]);
    }
}
