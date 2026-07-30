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
 * Author avatar uploader.
 *
 * Behaviourally identical to its parent — it exists purely to own a DI identity,
 * and the reason is worth writing down because it is not obvious.
 *
 * Magento_MediaGalleryCatalogIntegration registers the `save_category_image`
 * plugin on the concrete Magento\Catalog\Model\ImageUploader. That plugin pushes
 * every uploaded file into the media gallery, and its afterMoveFileFromTmp never
 * consults IsPathExcludedInterface, so no exclusion config can stop it. Firing on
 * an author avatar it throws, and before the move was isolated in Author/Save.php
 * that exception discarded the whole author record.
 *
 * The obvious fix — disable the plugin on the virtualType — DOES NOT WORK, and
 * silently so. A virtualType does not get an interceptor of its own: the instance
 * is a Magento\Catalog\Model\ImageUploader\Interceptor, and the interception trait
 * sets its subjectType to get_parent_class($this), i.e. the CONCRETE class. Plugin
 * lookup therefore happens against the concrete class and the virtualType's own
 * plugin config is never consulted. `PluginListInterface::getNext()` called with a
 * virtualType name will happily report no plugins, which makes the config look
 * correct while the plugin keeps running.
 *
 * A real subclass gets its own generated interceptor whose subjectType is this
 * class, so a per-type plugin disable actually applies. See etc/adminhtml/di.xml.
 *
 * No constructor here on purpose: the parent's is inherited, so the arguments
 * configured in etc/di.xml (baseTmpPath, basePath, allowedExtensions,
 * allowedMimeTypes) keep working unchanged.
 */
class AuthorImageUploader extends ImageUploader
{
}
