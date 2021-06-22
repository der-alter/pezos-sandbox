<?php

declare(strict_types=1);

namespace PezosSandbox\Infrastructure\Symfony\Controller\MemberArea;

use PezosSandbox\Application\AddToken;
use PezosSandbox\Application\ApplicationInterface;
use PezosSandbox\Application\FlashType;
use PezosSandbox\Application\UpdateToken;
use PezosSandbox\Domain\Model\Common\UserFacingError;
use PezosSandbox\Domain\Model\Token\CouldNotFindToken;
use PezosSandbox\Infrastructure\Symfony\Form\TokenForm;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @Route("/member-area")
 */
final class TokenController extends AbstractController
{
    private ApplicationInterface $application;
    private TranslatorInterface $translator;

    public function __construct(
        ApplicationInterface $application,
        TranslatorInterface $translator
    ) {
        $this->application = $application;
        $this->translator  = $translator;
    }

    /**
     * @Route("/tokens", name="app_token_list", methods={"GET"})
     */
    public function list(): Response
    {
        $tokens = $this->application->listTokensForAdmin();

        return $this->render('member_area/tokens/list.html.twig', [
            'tokens' => $tokens,
        ]);
    }

    /**
     * @Route("/tokens/new", name="app_token_new", methods={"GET", "POST"})
     */
    public function new(Request $request): Response
    {
        $tokenForm = $this->createForm(TokenForm::class);
        $tokenForm->handleRequest($request);

        if ($tokenForm->isSubmitted() && $tokenForm->isValid()) {
            $formData = $tokenForm->getData();

            try {
                $addToken = new AddToken(
                    $formData['contract'].
                        ($formData['id'] ? '_'.$formData['id'] : ''),
                    $formData['metadata'],
                    $formData['active']
                );

                $this->application->addToken($addToken);

                $this->redirectToRoute('app_token_list');
            } catch (\Exception $e) {
                $tokenForm->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('member_area/tokens/new.html.twig', [
            'tokenForm' => $tokenForm->createView(),
        ]);
    }

    /**
     * @Route("/tokens/edit/{address}", name="app_token_edit", methods={"GET", "POST"})
     */
    public function edit(Request $request): Response
    {
        $address = $request->attributes->get('address');
        try {
            $token = $this->application->getOneTokenByAddress($address);
        } catch (CouldNotFindToken $exception) {
            $this->convertToFlashMessage($exception);

            return $this->redirectToRoute('app_token_list');
        }
        $tokenForm = $this->createForm(TokenForm::class, [
            'contract' => $token->address()->contract(),
            'id'       => $token->address()->id(),
            'metadata' => $token->metadata(),
            'active'   => $token->isActive(),
        ]);
        $tokenForm->handleRequest($request);

        if ($tokenForm->isSubmitted() && $tokenForm->isValid()) {
            $formData = $tokenForm->getData();

            try {
                $updateToken = new UpdateToken(
                    $token->tokenId()->asString(),
                    $formData['contract'].
                        ($formData['id'] ? '_'.$formData['id'] : ''),
                    $formData['metadata'],
                    $formData['active']
                );

                $this->application->updateToken($updateToken);

                return $this->redirectToRoute('app_token_list');
            } catch (\Exception $e) {
                $tokenForm->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('member_area/tokens/edit.html.twig', [
            'tokenForm' => $tokenForm->createView(),
        ]);
    }

    /**
     * @Route("/tokens/toggle/{address}", name="app_token_toggle", methods={"POST"})
     */
    public function toggleActive(Request $request): Response
    {
        $address     = $request->attributes->get('address');
        $token       = $this->application->getOneTokenByAddress($address);
        $updateToken = new UpdateToken(
            $token->tokenId()->asString(),
            $token->address()->asString(),
            $token->metadata(),
            !$token->isActive()
        );

        $this->application->updateToken($updateToken);

        return $this->redirectToRoute('app_token_list');
    }

    private function convertToFlashMessage(UserFacingError $exception): void
    {
        $this->addFlash(
            FlashType::WARNING,
            $this->translator->trans(
                $exception->translationId(),
                $exception->translationParameters()
            )
        );
    }
}