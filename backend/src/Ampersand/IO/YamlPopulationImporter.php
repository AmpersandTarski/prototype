<?php

/*
 * This file is part of the Ampersand backend framework.
 *
 */

namespace Ampersand\IO;

use Ampersand\Exception\BadRequestException;
use Ampersand\Model;
use Psr\Log\LoggerInterface;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Importer for Ampersand population files written in YAML.
 *
 * YAML is offered as a human-friendlier surface syntax for the very same population
 * format the JSON importer reads (see JsonPopulationImporter):
 *
 *   atoms:
 *     - concept: C
 *       atoms: [a1, a2, ...]
 *   links:
 *     - relation: r[S*T]
 *       links:
 *         - { src: a, tgt: b }
 *
 * To guarantee that the two formats behave IDENTICALLY — no construct that YAML lets
 * through but JSON blocks, or vice versa — this class does not re-implement the import
 * semantics. It parses the YAML into the same data structure the JSON importer would see,
 * transcodes it to JSON, and hands that to JsonPopulationImporter. Downstream there is
 * exactly one importer: block pairing, concept/relation resolution, src/tgt validation
 * and transaction behaviour are literally the same code. Only the surface parser differs,
 * which is the whole point of supporting two formats.
 *
 * Because YAML 1.2 is a superset of JSON, a JSON document parsed as YAML yields the same
 * structure — so a file accepted here is accepted there and vice versa.
 *
 * Memory: unlike the JSON path, YAML parsing is not streaming — the whole document is held
 * in memory once (plus its JSON transcription). This is a deliberate trade-off: YAML is
 * meant for human-authored, modestly sized population files. Machine-exported, large
 * populations are JSON and take the streaming JSON path.
 *
 * @author Stef Joosten
 */
class YamlPopulationImporter
{
    public function __construct(
        protected Model $model,
        protected LoggerInterface $logger
    ) {
    }

    /**
     * Import a YAML population file. Must be called within an open transaction; the caller
     * closes it (and thereby decides commit/rollback based on the invariants).
     */
    public function importFile(string $filePath): void
    {
        $this->logger->info("Start import of YAML population file");

        // Parse YAML into the same structure the JSON importer works on.
        try {
            $data = Yaml::parseFile($filePath);
        } catch (ParseException $e) {
            throw new BadRequestException("Invalid population file: {$e->getMessage()}", previous: $e);
        }

        // Transcode to JSON and delegate to the single, shared population importer, so the
        // import semantics are identical to a JSON upload.
        $json = json_encode($data);
        if ($json === false) {
            throw new BadRequestException("Invalid population file: could not transcode YAML to JSON (" . json_last_error_msg() . ")");
        }

        $tmpJson = tempnam(sys_get_temp_dir(), 'pop_yaml_') ?: throw new BadRequestException("Could not create temporary file for YAML import");
        try {
            file_put_contents($tmpJson, $json);
            (new JsonPopulationImporter($this->model, $this->logger))->importFile($tmpJson);
        } finally {
            @unlink($tmpJson);
        }

        $this->logger->info("End import of YAML population file");
    }
}
