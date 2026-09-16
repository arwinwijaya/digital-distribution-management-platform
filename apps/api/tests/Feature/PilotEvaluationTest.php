<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\PilotEvaluationService;

class PilotEvaluationTest extends TestCase
{
    public function testScaleUpDecision(): void
    {
        $pilotResults = [
            'validOrders' => 25,
            'speedDelta' => [
                'deltaPercent' => -91.7,
                'targetMet' => true,
                'baselineHours' => 48.0,
                'platformHours' => 4.0,
            ],
            'reliability' => [
                'errorRate' => 4.0,
                'deliverySuccess' => 96.0,
                'paymentCompletion' => 95.8,
                'guardrailsPassed' => true,
            ],
            'pilotDays' => 7,
            'baselineHours' => 48.0,
        ];

        $service = new PilotEvaluationService();
        $result = $service->evaluate($pilotResults);

        $this->assertEquals('scale-up', $result['decision']);
        $this->assertArrayHasKey('kpiSummary', $result);
        $this->assertArrayHasKey('phase7Evidence', $result);
        $this->assertEquals(-91.7, $result['kpiSummary']['speedDelta']['deltaPercent']);
        $this->assertEquals(96.0, $result['kpiSummary']['reliability']['deliverySuccess']);
    }

    public function testIterateDecision(): void
    {
        $pilotResults = [
            'validOrders' => 20,
            'speedDelta' => [
                'deltaPercent' => -25.0,
                'targetMet' => false,
                'baselineHours' => 48.0,
                'platformHours' => 36.0,
            ],
            'reliability' => [
                'errorRate' => 3.0,
                'deliverySuccess' => 97.0,
                'paymentCompletion' => 92.0,
                'guardrailsPassed' => true,
            ],
            'pilotDays' => 7,
            'baselineHours' => 48.0,
        ];

        $service = new PilotEvaluationService();
        $result = $service->evaluate($pilotResults);

        $this->assertEquals('iterate', $result['decision']);
        $this->assertFalse($result['kpiSummary']['speedDelta']['targetMet']);
        $this->assertArrayHasKey('lessonsLearned', $result);
        $this->assertNotEmpty($result['lessonsLearned']);
    }

    public function testStopDecision(): void
    {
        $pilotResults = [
            'validOrders' => 18,
            'speedDelta' => [
                'deltaPercent' => -20.0,
                'targetMet' => false,
                'baselineHours' => 48.0,
                'platformHours' => 38.4,
            ],
            'reliability' => [
                'errorRate' => 8.0,
                'deliverySuccess' => 89.0,
                'paymentCompletion' => 85.0,
                'guardrailsPassed' => false,
            ],
            'pilotDays' => 7,
            'baselineHours' => 48.0,
        ];

        $service = new PilotEvaluationService();
        $result = $service->evaluate($pilotResults);

        $this->assertEquals('stop', $result['decision']);
        $this->assertArrayHasKey('rootCauseAnalysis', $result);
        $this->assertNotEmpty($result['rootCauseAnalysis']);
        $this->assertArrayHasKey('recommendations', $result);
        $this->assertNotEmpty($result['recommendations']);
    }

    public function testEvidenceGeneration(): void
    {
        $evaluation = [
            'decision' => 'scale-up',
            'guardrailsMet' => true,
            'kpiSummary' => [
                'validOrders' => 25,
                'volumeMet' => true,
                'speedDelta' => [
                    'deltaPercent' => -91.7,
                    'baselineHours' => 48.0,
                    'platformHours' => 4.0,
                    'targetMet' => true,
                ],
                'reliability' => [
                    'errorRate' => 4.0,
                    'deliverySuccess' => 96.0,
                    'paymentCompletion' => 95.8,
                    'guardrailsPassed' => true,
                    'errorCheck' => 'PASS',
                    'deliveryCheck' => 'PASS',
                    'paymentCheck' => 'PASS',
                ],
                'pilotDays' => 7,
            ],
            'phase7Evidence' => [
                'type' => 'case-study',
                'summary' => 'Pilot completed with 25 valid orders.',
            ],
        ];

        $service = new PilotEvaluationService();
        $evidence = $service->generateEvidence($evaluation);

        $this->assertArrayHasKey('executiveSummary', $evidence);
        $this->assertArrayHasKey('kpiDetail', $evidence);
        $this->assertArrayHasKey('recommendations', $evidence);
        $this->assertArrayHasKey('dataCollection', $evidence);

        $this->assertEquals('scale-up', $evidence['executiveSummary']['decision']);
        $this->assertEquals(25, $evidence['executiveSummary']['validOrders']);
        $this->assertEquals(7, $evidence['dataCollection']['pilotDurationDays']);
        $this->assertEquals(25, $evidence['dataCollection']['totalValidOrders']);
        $this->assertNotEmpty($evidence['kpiDetail']['speed']);
        $this->assertNotEmpty($evidence['kpiDetail']['reliability']);
    }
}
