<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

declare(strict_types=1);

namespace Magento\AdobeIms\Controller\Adminhtml\OAuth;

use Magento\AdobeImsApi\Api\Data\UserProfileInterface;
use Magento\AdobeImsApi\Api\Data\UserProfileInterfaceFactory;
use Magento\AdobeImsApi\Api\GetTokenInterface;
use Magento\AdobeImsApi\Api\UserProfileRepositoryInterface;
use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Exception\ConfigurationMismatchException;
use Magento\Framework\Exception\CouldNotSaveException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\User\Api\Data\UserInterface;
use Psr\Log\LoggerInterface;
use Magento\AdobeImsApi\Api\GetImageInterface;

/**
 * Callback action for managing user authentication with the Adobe services
 */
class Callback extends Action implements HttpGetActionInterface
{
    /**
     * @see _isAllowed()
     */
    public const ADMIN_RESOURCE = 'Magento_AdobeIms::login';

    /**
     * Constants of response
     *
     * RESPONSE_TEMPLATE - template of response
     * RESPONSE_SUCCESS_CODE success code
     * RESPONSE_ERROR_CODE error code
     */
    private const RESPONSE_TEMPLATE = 'auth[code=%s;message=%s]';
    private const RESPONSE_SUCCESS_CODE = 'success';
    private const RESPONSE_ERROR_CODE = 'error';

    /**
     * Constants of request
     *
     * REQUEST_PARAM_ERROR error
     * REQUEST_PARAM_CODE code
     */
    private const REQUEST_PARAM_ERROR = 'error';
    private const REQUEST_PARAM_CODE = 'code';

    /**
     * @var UserProfileRepositoryInterface
     */
    private $userProfileRepository;

    /**
     * @var UserProfileInterfaceFactory
     */
    private $userProfileFactory;

    /**
     * @var GetTokenInterface
     */
    private $getToken;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var GetImageInterface
     */
    private $getUserImage;

    /**
     * @param Action\Context $context
     * @param UserProfileRepositoryInterface $userProfileRepository
     * @param UserProfileInterfaceFactory $userProfileFactory
     * @param GetTokenInterface $getToken
     * @param LoggerInterface $logger
     * @param GetImageInterface $getImage
     */
    public function __construct(
        Action\Context $context,
        UserProfileRepositoryInterface $userProfileRepository,
        UserProfileInterfaceFactory $userProfileFactory,
        GetTokenInterface $getToken,
        LoggerInterface $logger,
        GetImageInterface $getImage
    ) {
        parent::__construct($context);

        $this->userProfileRepository = $userProfileRepository;
        $this->userProfileFactory = $userProfileFactory;
        $this->getToken = $getToken;
        $this->logger = $logger;
        $this->getUserImage = $getImage;
    }

    /**
     * @inheritdoc
     */
    public function execute(): ResultInterface
    {
        try {
            $this->validateCallbackRequest();
            $tokenResponse = $this->getToken->execute(
                (string)$this->getRequest()->getParam(self::REQUEST_PARAM_CODE)
            );
            $userImage = $this->getUserImage->execute($tokenResponse->getAccessToken());
            $userProfile = $this->getUserProfile();
            $userProfile->setName($tokenResponse->getName());
            $userProfile->setEmail($tokenResponse->getEmail());
            $userProfile->setImage($userImage);
            $userProfile->setUserId((int)$this->getUser()->getId());
            $userProfile->setAccessToken($tokenResponse->getAccessToken());
            $userProfile->setRefreshToken($tokenResponse->getRefreshToken());
            $userProfile->setAccessTokenExpiresAt(
                $this->getExpiresTime($tokenResponse->getExpiresIn())
            );

            $this->userProfileRepository->save($userProfile);

            $response = sprintf(
                self::RESPONSE_TEMPLATE,
                self::RESPONSE_SUCCESS_CODE,
                __('Authorization was successful')
            );
        } catch (AuthorizationException | ConfigurationMismatchException | CouldNotSaveException $exception) {
            $response = sprintf(
                self::RESPONSE_TEMPLATE,
                self::RESPONSE_ERROR_CODE,
                $exception->getMessage()
            );
        } catch (\Exception $exception) {
            $this->logger->critical($exception);
            $response = sprintf(
                self::RESPONSE_TEMPLATE,
                self::RESPONSE_ERROR_CODE,
                __('Something went wrong.')
            );
        }

        /** @var Raw $resultRaw */
        $resultRaw = $this->resultFactory->create(ResultFactory::TYPE_RAW);
        $resultRaw->setContents($response);

        return $resultRaw;
    }

    /**
     * Validate callback request from the Adobe OAth service
     *
     * @throws ConfigurationMismatchException
     */
    private function validateCallbackRequest(): void
    {
        $error = $this->getRequest()->getParam(self::REQUEST_PARAM_ERROR);
        if ($error) {
            $message = __(
                'An error occurred during the callback request from the Adobe service: %error',
                ['error' => $error]
            );
            throw new ConfigurationMismatchException($message);
        }
    }

    /**
     * Get user profile entity
     *
     * @return UserProfileInterface
     */
    private function getUserProfile(): UserProfileInterface
    {
        try {
            return $this->userProfileRepository->getByUserId(
                (int)$this->getUser()->getId()
            );
        } catch (NoSuchEntityException $exception) {
            return $this->userProfileFactory->create();
        }
    }

    /**
     * Get Authorised User
     *
     * @return UserInterface
     */
    private function getUser(): UserInterface
    {
        if (!$this->_auth->getUser() instanceof UserInterface) {
            throw new \RuntimeException('Auth user object must be an instance of UserInterface');
        }

        return $this->_auth->getUser();
    }

    /**
     * Retrieve token expires date
     *
     * @param int $expiresIn
     * @return string
     * @throws \Exception
     */
    private function getExpiresTime(int $expiresIn): string
    {
        $dateTime = new \DateTime();
        $dateTime->add(new \DateInterval(sprintf('PT%dS', $expiresIn / 1000)));
        return $dateTime->format('Y-m-d H:i:s');
    }
}
