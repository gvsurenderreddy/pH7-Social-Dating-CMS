<?php
/**
 * @author           Pierre-Henry Soria <hello@ph7cms.com>
 * @copyright        (c) 2017, Pierre-Henry Soria. All Rights Reserved.
 * @license          GNU General Public License; See PH7.LICENSE.txt and PH7.COPYRIGHT.txt in the root directory.
 * @package          PH7 / Framework / Service / SearchImage
 */

namespace PH7\Framework\Service\SearchImage;

defined('PH7') or exit('Restricted access');

use PH7\Framework\Url\Url;

class Google implements Imageable
{
    const SEARCH_IMAGE_LINK = 'https://www.google.com/searchbyimage?image_url=';

    /** @var string */
    private $sImageUrl;

    /**
     * @param string $sImageUrl
     */
    public function __construct($sImageUrl)
    {
        $this->sImageUrl = $sImageUrl;
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderUrl()
    {
        return static::SEARCH_IMAGE_LINK;
    }

    /**
     * {@inheritdoc}
     */
    public function getSearchImageUrl()
    {
        return static::SEARCH_IMAGE_LINK . Url::encode($this->sImageUrl);
    }
}
