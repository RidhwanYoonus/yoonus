<?php

namespace App\Filament\Concerns;

/**
 * After "Create" / "Save changes" the form closes and staff go back to the page they came from
 * (the list with its filters, search and page), or to the list when they opened the form directly.
 */
trait ReturnsAfterSave
{
    public ?string $returnTo = null;

    public function mountReturnsAfterSave(): void
    {
        $previous = (string) url()->previous();
        $here = url()->current();
        $sameAdmin = str_starts_with($previous, url('/admin')) && ! preg_match('#/(create|edit|login)(\?|$|/)#', $previous);
        if ($previous !== '' && $previous !== $here && $sameAdmin) {
            $this->returnTo = $previous;
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->returnTo ?: static::getResource()::getUrl('index');
    }
}
