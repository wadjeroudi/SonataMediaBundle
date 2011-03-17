<?php
/*
 * This file is part of the Sonata project.
 *
 * (c) Thomas Rabaix <thomas.rabaix@sonata-project.org>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonata\MediaBundle\Provider;

use Sonata\MediaBundle\Entity\BaseMedia as Media;
use Symfony\Component\Form\Form;
use Sonata\AdminBundle\Form\FormMapper;

class DailyMotionProvider extends BaseProvider
{

    public function getReferenceImage(Media $media)
    {

        return $media->getMetadataValue('thumbnail_url');
    }

    public function getAbsolutePath(Media $media)
    {

        return sprintf('http://www.dailymotion.com/swf/video/%s', $media->getProviderReference());
    }

    /**
     * build the related create form
     *
     */
    function buildEditForm(FormMapper $formMapper)
    {
        $formMapper->add('name');
        $formMapper->add('enabled');
        $formMapper->add('authorName');
        $formMapper->add('cdnIsFlushable');
        $formMapper->add('description');
        $formMapper->add('copyright');
        $formMapper->add('binaryContent', array(), array('type' => 'string'));
    }

    /**
     * build the related create form
     *
     */
    function buildCreateForm(FormMapper $formMapper)
    {
        $formMapper->add('binaryContent', array(), array('type' => 'string'));
    }

    public function getHelperProperties(Media $media, $format, $options = array())
    {

        // documentation : http://www.dailymotion.com/en/doc/api/player

        $defaults = array(
            // Values: 0 or 1. Default is 0. Determines if the player loads related videos when
            // the current video begins playback.
            'related'   => 0,

            // Values: 0 or 1. Default is 1. Determines if the player allows explicit content to
            // be played. This parameter may be added to embed code by platforms which do not
            // want explicit content to be posted by their users.
            'explicit'  => 0,

            // Values: 0 or 1. Default is 0. Determines if the video will begin playing
            // automatically when the player loads.
            'autoPlay'      => 0,

            // Values: 0 or 1. Default is 0. Determines if the video will begin muted.
            'autoMute' => 0,

            // Values: 0 or 1. Default is 0. Determines if the video will unmuted on mouse over.
            // Of course it works only if the player is on automute=1.
            'unmuteOnMouseOver' => 0,

            // Values: a number of seconds. Default is 0. Determines if the video will begin
            // playing the video at a given time.
            'start' => 0,

            // Values: 0 or 1. Default is 0. Enable the Javascript API by setting this parameter
            // to 1. For more information and instructions on using the Javascript API, see the
            // JavaScript API documentation.
            'enableApi' => 0,

            // Values: 0 or 1. Default is 0. Determines if the player should display controls
            // or not during video playback.
            'chromeless' => 0,

            // Values: 0 or 1. Default is 0. Determines if the video should be expended to fit
            // the whole player's size.
            'expendVideo' => 0,
            'color2' => null,

            // Player color changes may be set using color codes. A color is described by its
            // hexadecimal value (eg: FF0000 for red).
            'foreground' => null,
            'background' => null,
            'highlight' => null,
        );


        $player_parameters =  array_merge($defaults, isset($options['player_parameters']) ? $options['player_parameters'] : array());

        $params = array(
            'player_parameters' => http_build_query($player_parameters),
            'allowFullScreen'   => isset($options['allowFullScreen'])   ? $options['allowFullScreen']     : 'true',
            'allowScriptAccess' => isset($options['allowScriptAccess']) ? $options['allowScriptAccess'] : 'always',
            'width'             => isset($options['width'])             ? $options['width']  : $media->getWidth(),
            'height'            => isset($options['height'])            ? $options['height'] : $media->getHeight(),
        );

        return $params;
    }

    /**
     * @param \Sonata\MediaBundle\Entity\BaseMedia $media
     * @return
     */
    public function prePersist(Media $media)
    {

        if (!$media->getBinaryContent()) {

            return;
        }

        $metadata = $this->getMetadata($media);
        
        $media->setProviderName($this->name);
        $media->setProviderMetadata($metadata);
        $media->setProviderReference($media->getBinaryContent());
        $media->setName($metadata['title']);
        $media->setAuthorName($metadata['author_name']);
        $media->setHeight($metadata['height']);
        $media->setWidth($metadata['width']);
        $media->setContentType('video/x-flv');
        $media->setProviderStatus(Media::STATUS_OK);

        $media->setCreatedAt(new \Datetime());
        $media->setUpdatedAt(new \Datetime());
    }

    /**
     * @param \Sonata\MediaBundle\Entity\BaseMedia $media
     * @return
     */
    public function preUpdate(Media $media)
    {
        if (!$media->getBinaryContent()) {

            return;
        }

        $metadata = $this->getMetadata($media);

        $media->setProviderMetadata($metadata);
        $media->setProviderReference($media->getBinaryContent());
        $media->setHeight($metadata['height']);
        $media->setWidth($metadata['width']);
        $media->setProviderStatus(Media::STATUS_OK);
        
        $media->setUpdatedAt(new \Datetime());
    }

    /**
     * @throws \RuntimeException
     * @param  $media
     * @return mixed|string
     */
    public function getMetadata($media)
    {

        if (!$media->getBinaryContent()) {

            return;
        }

        $url = sprintf('http://www.dailymotion.com/services/oembed?url=http://www.dailymotion.com/video/%s&format=json', $media->getBinaryContent());
        $metadata = @file_get_contents($url);

        if (!$metadata) {
            throw new \RuntimeException('Unable to retrieve dailymotion video information for :' . $url);
        }

        $metadata = json_decode($metadata, true);

        if (!$metadata) {
            throw new \RuntimeException('Unable to decode dailymotion video information for :' . $url);
        }

        return $metadata;
    }

    /**
     * @param \Sonata\MediaBundle\Entity\BaseMedia $media
     * @return void
     */
    public function postUpdate(Media $media)
    {
        $this->postPersist($media);
    }

    /**
     * @param \Sonata\MediaBundle\Entity\BaseMedia $media
     * @return
     */
    public function postPersist(Media $media)
    {
        if (!$media->getBinaryContent()) {
            return;
        }

        $this->generateThumbnails($media);
    }

    /**
     * @param \Sonata\MediaBundle\Entity\BaseMedia $media
     * @return void
     */
    public function preRemove(Media $media)
    {

    }
}