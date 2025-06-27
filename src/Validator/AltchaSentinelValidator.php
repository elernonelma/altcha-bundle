<?php

declare(strict_types=1);

namespace Huluti\AltchaBundle\Validator;

use AltchaOrg\Altcha\Altcha;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

final class AltchaSentinelValidator extends ConstraintValidator
{
    public function __construct(
        private readonly bool $enable,
        private readonly string $apiKey,
        private readonly string $verifySignatureUrl,
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack,
    ) {
    }

    /**
     * Checks if the passed value is valid.
     *
     * @param mixed      $value      The value that should be validated
     * @param Constraint $constraint The constraint for the validation
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (false === $this->enable) {
            return;
        }

        if (!$value) {
            $request = $this->requestStack->getCurrentRequest();
            $value = $request?->request->get('altcha');
        }

        if (!is_string($value)) {
            /**
             * If the client did not sent any payload, it may be due to server beeing
             * not available, we will verify that while calling it.
             */
            $value = '';
        }

        $response = $this->httpClient->request('POST', $this->verifySignatureUrl, [
            'json' => ['payload' => $value],
        ]);
        try {
            if (200 === $response->getStatusCode()) {
                $responseContent = $response->toArray();
                if (
                    array_key_exists('verified', $responseContent)
                    && true === $responseContent['verified']
                    && array_key_exists('apiKey', $responseContent)
                    && $this->apiKey === $responseContent['apiKey']
                ) {
                    return;
                }
            }
        } catch (HttpExceptionInterface $e) {
            // @todo add error log
            // we consider that when the server fails to deliver a verification, we let the user continue.
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->addviolation();
    }
}
