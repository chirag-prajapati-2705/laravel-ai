<?php

declare(strict_types=1);

namespace App\Neuron;

use NeuronAI\RAG\DataLoader\PdfReader;

class WindowsPdfReader extends PdfReader
{
    protected function findBinary(string $binaryName): string
    {
        $executableName = PHP_OS_FAMILY === 'Windows' && ! str_ends_with(strtolower($binaryName), '.exe')
            ? $binaryName.'.exe'
            : $binaryName;

        if (isset($this->binPath)) {
            $basePath = dirname($this->binPath);
            $candidatePath = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$executableName;

            if (is_executable($candidatePath)) {
                return $candidatePath;
            }
        }

        foreach ($this->commonBasePaths as $basePath) {
            $candidatePath = rtrim($basePath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$executableName;
            if (is_executable($candidatePath)) {
                return $candidatePath;
            }
        }

        return parent::findBinary($binaryName);
    }
}
