<?php
interface WordFilter
{
    public function apply(string $text): string;
}

class FunnyFilter implements WordFilter
{

    private array $badWords = [
        'smh' => 'baka',
        'SMH' => 'BAKA',
        'tbh' => 'desu',
        'TBH' => 'DESU',
        'fam' => 'senpai',
        'FAM' => 'SENPAI',
        'Fam' => 'Senpai',
        'fams' => 'senpaitachi',
        'FAMS' => 'SENPAITACHI',
        'FAMs' => 'SENPAITACHI',
        'Fams' => 'Senpaitachi'
    ];

    public function apply(string $text): string
    {
        foreach ($this->badWords as $word => $replacement) {
            if ($replacement === null) {
                $replacement = str_repeat('*', strlen($word));
            }

            $text = preg_replace("/\b" . preg_quote($word, '/') . "\b/i", $replacement, $text);
        }
        return $text;
    }
}

class UrlFilter implements WordFilter
{
    public function apply(string $text): string
    {
        return preg_replace('/https?:\/\/\S+/i', '[link removed]', $text);
    }
}

class CapsFilter implements WordFilter
{
    public function apply(string $text): string
    {
        if (strtoupper($text) === $text && strlen($text) > 5) {
            return ucfirst(strtolower($text));
        }
        return $text;
    }
}

class FilterManager
{
    /** @var WordFilter[] */
    private array $filters = [];

    public function addFilter(WordFilter $filter): void
    {
        $this->filters[] = $filter;
    }

    public function applyFilters(string $text): string
    {
        foreach ($this->filters as $filter) {
            $text = $filter->apply($text);
        }
        return $text;
    }
}
