<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\Core\Concept;
use Psr\Http\Message\StreamInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Ampersand\Core\Atom;
use Ampersand\AmpersandApp;

class Exporter
{

    /**
     * Logger
     *
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Undocumented variable
     *
     * @var \Symfony\Component\Serializer\Encoder\EncoderInterface
     */
    protected $encoder;

    /**
     * Stream to export to
     *
     * @var \Psr\Http\Message\StreamInterface
     */
    protected $stream;
    
    /**
     * Constructor
     *
     * @param \Symfony\Component\Serializer\Encoder\EncoderInterface $encoder
     * @param \Psr\Http\Message\StreamInterface $stream
     * @param \Psr\Log\LoggerInterface $logger
     * @param array $options
     */
    public function __construct(EncoderInterface $encoder, StreamInterface $stream, LoggerInterface $logger, array $options = [])
    {
        $this->logger = $logger;
        $this->encoder = $encoder;
        $this->stream = $stream;
    }

    public function exportAllPopulation(AmpersandApp $app, string $format)
    {
        $conceptPop = [];
        foreach (Concept::getAllConcepts() as $concept) {
            $conceptPop[] = [
                'concept' => $concept->name,
                'atoms' => array_map(function (Atom $atom) {
                    return $atom->getId();
                }, $concept->getAllAtomObjects())
            ];
        }
        
        $relationPop = [];
        foreach ($app->getModel()->getRelations() as $rel) {
            $relationPop[] = [
                'relation' => $rel->signature,
                'links' => $rel->getAllLinks()
            ];
        }

        $this->stream->write($this->encoder->encode(['atoms' => $conceptPop, 'links' => $relationPop], $format));

        return $this;
    }
}
