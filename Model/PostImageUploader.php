<?php
/**
 * Copyright (c) 2025 Content Basis LLC
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available at https://opensource.org/licenses/OSL-3.0
 *
 * @category  RequestDesk
 * @package   RequestDesk_Blog
 * @author    Content Basis LLC
 * @copyright Copyright (c) 2025 Content Basis LLC
 * @license   https://opensource.org/licenses/OSL-3.0 Open Software License 3.0
 */
declare(strict_types=1);

namespace RequestDesk\Blog\Model;

use Magento\Catalog\Model\ImageUploader;

/**
 * Post featured-image uploader.
 *
 * A real subclass rather than a virtualType for the same reason as
 * AuthorImageUploader: only a concrete class gets its own interceptor, so only
 * then does disabling Magento's `save_category_image` plugin in
 * etc/adminhtml/di.xml actually take effect. Arguments live in etc/di.xml.
 */
class PostImageUploader extends ImageUploader
{
}
