<?php

declare(strict_types=1);

/*
 * This file is part of SAC Event Tool Bundle.
 *
 * (c) Marko Cupic <m.cupic@gmx.ch>
 * @license GPL-3.0-or-later
 * For the full copyright and license information,
 * please view the LICENSE file that was distributed with this source code.
 * @link https://github.com/markocupic/sac-event-tool-bundle
 */

namespace Markocupic\SacEventToolBundle\Controller;

use Contao\CoreBundle\Csrf\ContaoCsrfTokenManager;
use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\FrontendUser;
use Contao\MemberModel;
use Contao\System;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class PasswordController extends AbstractController
{
	public function __construct(
		private readonly ContaoFramework $framework,
		private readonly Connection $connection,
		private readonly ContaoCsrfTokenManager $csrfTokenManager,
	) {
	}

	#[Route('/passwordhasher_form', name: 'passwordhasher_form', defaults: ['_scope' => 'frontend', '_token_check' => true], methods: ['GET'])]
	public function passwordhasherForm(): Response
	{
		$this->framework->initialize();
		$csrfToken = $this->csrfTokenManager->getDefaultTokenValue();

		return $this->render('@MarkocupicSacEventTool/password_hasher.html.twig', ['max' => $this->getMax(), 'csrf_token' => $csrfToken]);
	}

	#[Route('/hash_users', name: 'hash_users', defaults: ['_scope' => 'frontend', '_token_check' => true], methods: ['POST'])]
	public function hashUsers(Request $request): JsonResponse
	{
		if ($request->request->get('REQUEST_TOKEN')) {
			$container = $this->framework->getAdapter(System::class)->getContainer();

			$passwordHasher = $container->get('security.password_hasher_factory')->getPasswordHasher(FrontendUser::class);

			$ids = $this->getUserIds((int) $request->request->get('offset'), (int) $request->request->get('limit'));

			$pws = [];

			foreach ($ids as $id) {
				$user = MemberModel::findById($id);
				$objUser = MemberModel::findById($user->id);
				$objUser->pwChange = false;
				$objUser->password = $passwordHasher->hash($this->generateSecurePassword());
				$objUser->save();

				$pws[$id] = 'tl_member.id '.$id.' done';
			}
		}

		if (!empty($pws)) {
			$json = ['success' => true, 'pws' => $pws];
		} else {
			$json = ['success' => false];
		}

		return new JsonResponse($json);
	}

	public function generateSecurePassword($length = 16): string
	{
		if ($length < 16) {
			throw new \Exception('Password length must be at least 16');
		}

		$upper = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
		$lower = 'abcdefghijklmnopqrstuvwxyz';
		$digits = '0123456789';
		$special = '!@#$%^&*()-_=+[]{}<>?';
		$all = $upper.$lower.$digits.$special;

		$password = [
			$upper[random_int(0, \strlen($upper) - 1)],
			$digits[random_int(0, \strlen($digits) - 1)],
			$special[random_int(0, \strlen($special) - 1)],
		];

		while (\count($password) < $length) {
			$password[] = $all[random_int(0, \strlen($all) - 1)];
		}

		shuffle($password);

		return implode('', $password);
	}

	private function getMax(): int
	{
		return (int) $this->connection->fetchOne('SELECT COUNT(id) FROM tl_member');
	}

	private function getUserIds(int $offset, int $limit): array
	{
		$sql = \sprintf('SELECT id FROM tl_member ORDER BY id LIMIT %d,%d', $offset, $limit);

		return $this->connection->fetchFirstColumn($sql);
	}
}
