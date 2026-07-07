# 07 - Evaluators

## Goal

Evaluate each assistant run using deterministic and extensible evaluators.

## Tasks

- [ ] Create `EvaluatorInterface`.
- [ ] Create `EvaluationInput`.
- [ ] Create `EvaluationResult`.
- [ ] Implement `FunctionalEvaluator`.
- [ ] Implement `RootCauseEvaluator`.
- [ ] Implement `DiffMinimalityEvaluator`.
- [ ] Implement `ForbiddenChangesEvaluator`.
- [ ] Implement `VerificationEvaluator`.
- [ ] Implement `MateToolUsageEvaluator`.
- [ ] Implement `EfficiencyEvaluator`.

## Evaluator Interface

```php
interface EvaluatorInterface
{
    public function evaluate(EvaluationInput $input): EvaluationResult;
}
```

## Scoring

Each evaluator returns:

- [ ] score from `0..5`
- [ ] pass/fail where applicable
- [ ] human-readable explanation
- [ ] machine-readable evidence

## Acceptance Criteria

- [ ] Functional evaluator reruns expected pass commands.
- [ ] Forbidden file changes fail the scenario or heavily penalize it.
- [ ] Mate tool usage evaluator can check expected tool names.
- [ ] Root cause evaluator starts rule-based and can later be replaced with LLM judge.
