<?php
declare(strict_types=1);

namespace Panth\Blog\Controller\Adminhtml\Image;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Catalog\Model\ImageUploader;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Controller\ResultInterface;
use Magento\Framework\Exception\LocalizedException;

class Upload extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_Blog::all';

    private const SAVE_RESOURCES = [
        'Panth_Blog::post_save',
        'Panth_Blog::category_save',
        'Panth_Blog::author_save',
    ];

    private const ALLOWED_FIELDS = ['featured_image', 'og_image', 'image', 'avatar'];

    private const ALLOWED_TYPES = [
        'jpg' => ['image/jpeg', 'image/pjpeg'],
        'jpeg' => ['image/jpeg', 'image/pjpeg'],
        'gif' => ['image/gif'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
    ];

    public function __construct(
        Context $context,
        private readonly ImageUploader $imageUploader
    ) {
        parent::__construct($context);
    }

    public function execute(): ResultInterface
    {
        $field = (string) $this->getRequest()->getParam('param_name', '');
        try {
            if (!in_array($field, self::ALLOWED_FIELDS, true)) {
                throw new LocalizedException(__('Unknown image field.'));
            }
            $file = $this->getRequest()->getFiles($field);
            if (!is_array($file) || !isset($file['name'], $file['tmp_name'])
                || !is_string($file['name']) || !is_string($file['tmp_name'])
            ) {
                throw new LocalizedException(__('No image was uploaded.'));
            }
            $this->assertAllowedImage($file['name'], $file['tmp_name']);
            $result = $this->imageUploader->saveFileToTmpDir($field);
        } catch (\Exception $e) {
            $result = ['error' => $e->getMessage(), 'errorcode' => $e->getCode()];
        }

        return $this->resultFactory->create(ResultFactory::TYPE_JSON)->setData($result);
    }

    protected function _isAllowed(): bool
    {
        foreach (self::SAVE_RESOURCES as $resource) {
            if ($this->_authorization->isAllowed($resource)) {
                return true;
            }
        }
        return false;
    }

    private function assertAllowedImage(string $name, string $tmpName): void
    {
        $message = __('Only JPG, PNG, GIF and WebP images can be uploaded.');
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED_TYPES[$extension]) || $tmpName === '' || !is_file($tmpName)) {
            throw new LocalizedException($message);
        }
        $mimeType = strtolower((string) (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName));
        if (!in_array($mimeType, self::ALLOWED_TYPES[$extension], true)) {
            throw new LocalizedException($message);
        }
        try {
            $imageInfo = getimagesize($tmpName);
        } catch (\Throwable $e) {
            $imageInfo = false;
        }
        if ($imageInfo === false
            || !in_array(strtolower((string) $imageInfo['mime']), self::ALLOWED_TYPES[$extension], true)
        ) {
            throw new LocalizedException($message);
        }
    }
}
