<?php

namespace App\Services;

class PilotEvaluationService
{
    public const DECISION_SCALE_UP = 'scale-up';
    public const DECISION_ITERATE = 'iterate';
    public const DECISION_STOP = 'stop';

    public const SPEED_TARGET_MET = true;
    public const ERROR_RATE_LIMIT = 5.0;
    public const DELIVERY_SUCCESS_LIMIT = 95.0;
    public const PAYMENT_COMPLETION_LIMIT = 90.0;
    public const MIN_VALID_ORDERS = 20;

    /**
     * Evaluate pilot results against KPI thresholds and return a decision.
     *
     * @param array{
     *   validOrders: int,
     *   speedDelta: array{deltaPercent: float, targetMet: bool, baselineHours: float, platformHours: float},
     *   reliability: array{errorRate: float, deliverySuccess: float, paymentCompletion: float, guardrailsPassed: bool},
     *   pilotDays?: int,
     *   baselineHours?: float,
     * } $pilotResults
     * @return array{
     *   decision: string,
     *   kpiSummary: array,
     *   guardrailsMet: bool,
     *   phase7Evidence: array|null,
     *   lessonsLearned: array|null,
     *   rootCauseAnalysis: array|null,
     *   recommendations: array|null,
     * }
     */
    public function evaluate(array $pilotResults): array
    {
        $validOrders = $pilotResults['validOrders'] ?? 0;
        $speedDelta = $pilotResults['speedDelta'] ?? [];
        $reliability = $pilotResults['reliability'] ?? [];

        $speedTargetMet = $speedDelta['targetMet'] ?? false;
        $guardrailsPassed = $reliability['guardrailsPassed'] ?? false;

        $errorRate = $reliability['errorRate'] ?? 0.0;
        $deliverySuccess = $reliability['deliverySuccess'] ?? 0.0;
        $paymentCompletion = $reliability['paymentCompletion'] ?? 0.0;

        $kpiSummary = [
            'validOrders' => $validOrders,
            'volumeMet' => $validOrders >= self::MIN_VALID_ORDERS,
            'speedDelta' => [
                'deltaPercent' => $speedDelta['deltaPercent'] ?? 0.0,
                'baselineHours' => $speedDelta['baselineHours'] ?? $pilotResults['baselineHours'] ?? 0.0,
                'platformHours' => $speedDelta['platformHours'] ?? 0.0,
                'targetMet' => $speedTargetMet,
            ],
            'reliability' => [
                'errorRate' => $errorRate,
                'deliverySuccess' => $deliverySuccess,
                'paymentCompletion' => $paymentCompletion,
                'guardrailsPassed' => $guardrailsPassed,
                'errorCheck' => $errorRate < self::ERROR_RATE_LIMIT ? 'PASS' : 'FAIL',
                'deliveryCheck' => $deliverySuccess > self::DELIVERY_SUCCESS_LIMIT ? 'PASS' : 'FAIL',
                'paymentCheck' => $paymentCompletion > self::PAYMENT_COMPLETION_LIMIT ? 'PASS' : 'FAIL',
            ],
            'pilotDays' => $pilotResults['pilotDays'] ?? 7,
        ];

        $volumeMet = $validOrders >= self::MIN_VALID_ORDERS;

        // Stop: guardrails violated
        if (!$guardrailsPassed) {
            return array_merge([
                'decision' => self::DECISION_STOP,
                'guardrailsMet' => false,
                'phase7Evidence' => null,
                'lessonsLearned' => null,
                'rootCauseAnalysis' => $this->buildRootCauseAnalysis($kpiSummary),
                'recommendations' => $this->buildStopRecommendations($kpiSummary),
            ], ['kpiSummary' => $kpiSummary]);
        }

        // Scale-up: volume met + speed target met + guardrails passed
        if ($volumeMet && $speedTargetMet && $guardrailsPassed) {
            return array_merge([
                'decision' => self::DECISION_SCALE_UP,
                'guardrailsMet' => true,
                'phase7Evidence' => $this->buildPhase7Evidence($kpiSummary),
                'lessonsLearned' => null,
                'rootCauseAnalysis' => null,
                'recommendations' => null,
            ], ['kpiSummary' => $kpiSummary]);
        }

        // Iterate: guardrails met but volume or speed not met
        return array_merge([
            'decision' => self::DECISION_ITERATE,
            'guardrailsMet' => true,
            'phase7Evidence' => null,
            'lessonsLearned' => $this->buildLessonsLearned($kpiSummary),
            'rootCauseAnalysis' => null,
            'recommendations' => null,
        ], ['kpiSummary' => $kpiSummary]);
    }

    /**
     * Generate Phase 7 evidence document from an evaluation result.
     *
     * @param array $evaluation Output of evaluate()
     * @return array{executiveSummary: array, kpiDetail: array, recommendations: array, dataCollection: array}
     */
    public function generateEvidence(array $evaluation): array
    {
        $kpiSummary = $evaluation['kpiSummary'] ?? [];
        $decision = $evaluation['decision'] ?? 'unknown';

        $speedDelta = $kpiSummary['speedDelta'] ?? [];
        $reliability = $kpiSummary['reliability'] ?? [];

        $executiveSummary = [
            'decision' => $decision,
            'validOrders' => $kpiSummary['validOrders'] ?? 0,
            'pilotDays' => $kpiSummary['pilotDays'] ?? 7,
            'speedImprovement' => $speedDelta['deltaPercent'] ?? 0.0,
            'targetMet' => $speedDelta['targetMet'] ?? false,
            'guardrailsMet' => $reliability['guardrailsPassed'] ?? false,
        ];

        $kpiDetail = [
            'speed' => [
                'baselineHours' => $speedDelta['baselineHours'] ?? 0.0,
                'platformHours' => $speedDelta['platformHours'] ?? 0.0,
                'deltaPercent' => $speedDelta['deltaPercent'] ?? 0.0,
                'targetPercent' => -30.0,
                'targetMet' => $speedDelta['targetMet'] ?? false,
            ],
            'reliability' => [
                'errorRate' => $reliability['errorRate'] ?? 0.0,
                'errorLimit' => self::ERROR_RATE_LIMIT,
                'errorCheck' => $reliability['errorCheck'] ?? 'FAIL',
                'deliverySuccess' => $reliability['deliverySuccess'] ?? 0.0,
                'deliveryLimit' => self::DELIVERY_SUCCESS_LIMIT,
                'deliveryCheck' => $reliability['deliveryCheck'] ?? 'FAIL',
                'paymentCompletion' => $reliability['paymentCompletion'] ?? 0.0,
                'paymentLimit' => self::PAYMENT_COMPLETION_LIMIT,
                'paymentCheck' => $reliability['paymentCheck'] ?? 'FAIL',
            ],
        ];

        $recommendations = $this->buildEvidenceRecommendations($decision, $kpiSummary);

        $dataCollection = [
            'pilotDurationDays' => $kpiSummary['pilotDays'] ?? 7,
            'totalValidOrders' => $kpiSummary['validOrders'] ?? 0,
            'collectionMethod' => 'Concierge pilot — internal team executing order-to-payment workflow',
            'baselineSource' => 'Manual process documentation provided by partner',
        ];

        return [
            'executiveSummary' => $executiveSummary,
            'kpiDetail' => $kpiDetail,
            'recommendations' => $recommendations,
            'dataCollection' => $dataCollection,
        ];
    }

    private function buildPhase7Evidence(array $kpiSummary): array
    {
        return [
            'type' => 'case-study',
            'summary' => sprintf(
                'Pilot completed with %d valid orders over %d days. Speed improved by %.1f%% against baseline. All guardrails passed.',
                $kpiSummary['validOrders'] ?? 0,
                $kpiSummary['pilotDays'] ?? 7,
                $kpiSummary['speedDelta']['deltaPercent'] ?? 0.0
            ),
            'keyMetrics' => [
                'validOrders' => $kpiSummary['validOrders'] ?? 0,
                'speedDelta' => $kpiSummary['speedDelta']['deltaPercent'] ?? 0.0,
                'errorRate' => $kpiSummary['reliability']['errorRate'] ?? 0.0,
                'deliverySuccess' => $kpiSummary['reliability']['deliverySuccess'] ?? 0.0,
                'paymentCompletion' => $kpiSummary['reliability']['paymentCompletion'] ?? 0.0,
            ],
        ];
    }

    private function buildLessonsLearned(array $kpiSummary): array
    {
        $lessons = [];

        if (!($kpiSummary['volumeMet'] ?? false)) {
            $lessons[] = [
                'area' => 'Volume',
                'finding' => sprintf('Only %d valid orders completed (target: %d)', $kpiSummary['validOrders'] ?? 0, self::MIN_VALID_ORDERS),
                'recommendation' => 'Extend pilot duration or increase concierge capacity',
            ];
        }

        if (!($kpiSummary['speedDelta']['targetMet'] ?? false)) {
            $delta = $kpiSummary['speedDelta']['deltaPercent'] ?? 0.0;
            $lessons[] = [
                'area' => 'Speed',
                'finding' => sprintf('Speed delta %.1f%% did not meet -30%% target', $delta),
                'recommendation' => 'Analyze bottleneck in order-to-delivery workflow and optimize',
            ];
        }

        return $lessons;
    }

    private function buildRootCauseAnalysis(array $kpiSummary): array
    {
        $reliability = $kpiSummary['reliability'] ?? [];
        $issues = [];

        if (($reliability['errorCheck'] ?? '') === 'FAIL') {
            $issues[] = [
                'metric' => 'Error Rate',
                'value' => $reliability['errorRate'] ?? 0.0,
                'threshold' => self::ERROR_RATE_LIMIT,
                'impact' => 'High error rate indicates process or data quality issues',
            ];
        }

        if (($reliability['deliveryCheck'] ?? '') === 'FAIL') {
            $issues[] = [
                'metric' => 'Delivery Success',
                'value' => $reliability['deliverySuccess'] ?? 0.0,
                'threshold' => self::DELIVERY_SUCCESS_LIMIT,
                'impact' => 'Low delivery success rate suggests logistics or coordination problems',
            ];
        }

        if (($reliability['paymentCheck'] ?? '') === 'FAIL') {
            $issues[] = [
                'metric' => 'Payment Completion',
                'value' => $reliability['paymentCompletion'] ?? 0.0,
                'threshold' => self::PAYMENT_COMPLETION_LIMIT,
                'impact' => 'Payment completion below threshold indicates invoicing or collection issues',
            ];
        }

        return [
            'rootCauses' => $issues,
            'severity' => 'high',
            'summary' => sprintf(
                '%d guardrail(s) violated — pilot does not meet minimum quality bar',
                count($issues)
            ),
        ];
    }

    private function buildStopRecommendations(array $kpiSummary): array
    {
        $reliability = $kpiSummary['reliability'] ?? [];
        $recommendations = [];

        if (($reliability['errorCheck'] ?? '') === 'FAIL') {
            $recommendations[] = 'Conduct root cause analysis on error sources before re-piloting';
        }

        if (($reliability['deliveryCheck'] ?? '') === 'FAIL') {
            $recommendations[] = 'Review delivery workflow and partner logistics coordination';
        }

        if (($reliability['paymentCheck'] ?? '') === 'FAIL') {
            $recommendations[] = 'Audit invoice generation and payment collection process';
        }

        $recommendations[] = 'Require significant iteration on core workflow before next pilot attempt';

        return $recommendations;
    }

    private function buildEvidenceRecommendations(string $decision, array $kpiSummary): array
    {
        if ($decision === self::DECISION_SCALE_UP) {
            return [
                'Proceed to multi-partner rollout planning',
                'Document case study for partner acquisition materials',
                'Establish production monitoring and alerting',
                'Plan Phase 7 MVP completion milestones',
            ];
        }

        if ($decision === self::DECISION_ITERATE) {
            $recommendations = [];

            if (!($kpiSummary['speedDelta']['targetMet'] ?? false)) {
                $recommendations[] = 'Optimize order-to-delivery workflow to meet speed target';
            }

            if (!($kpiSummary['volumeMet'] ?? false)) {
                $recommendations[] = 'Extend pilot duration or increase concierge capacity';
            }

            $recommendations[] = 'Document lessons learned for next pilot iteration';

            return $recommendations;
        }

        // stop
        return [
            'Complete root cause analysis before re-piloting',
            'Address identified guardrail failures',
            'Consider significant workflow redesign',
        ];
    }
}
