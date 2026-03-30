<?php

namespace App\Service;

use App\Entity\Page;
use App\Entity\Utilisateur;
use App\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class DynamicPageService
{
    public const PAGES_MODULE = 'pages';
    public const PUBLIC_ROUTE = 'app_dynamic_page_show';
    public const MANAGED_PAGE_PREFIX = 'dynamic_page_';

    public function __construct(
        private PageRepository $pageRepository,
        private EntityManagerInterface $em,
        private SluggerInterface $slugger,
        private FileUploadService $fileUploadService,
        private RequestStack $requestStack,
        private UrlGeneratorInterface $urlGenerator,
        private PageDisplayService $pageDisplayService,
        private MenuConfigService $menuConfigService,
    ) {}

    public static function buildManagedPagePathFromId(int $id): string
    {
        return self::MANAGED_PAGE_PREFIX . max(0, $id);
    }

    public function buildManagedPagePath(Page $page): string
    {
        return self::buildManagedPagePathFromId((int) ($page->getId() ?? 0));
    }

    public function listAll(): array
    {
        return $this->pageRepository->findAllSorted();
    }

    public function find(int $id): ?Page
    {
        return $id > 0 ? $this->pageRepository->find($id) : null;
    }

    public function findActiveBySlug(string $slug): ?Page
    {
        $slug = trim($slug);

        return $slug !== '' ? $this->pageRepository->findOneActiveBySlug($slug) : null;
    }

    public function createPage(
        string $title,
        string $content,
        string $keywords = '',
        ?Utilisateur $createdBy = null,
        bool $isActive = true,
        bool $showTitle = true,
        bool $showBreadcrumb = true,
    ): Page {
        $page = (new Page())
            ->setTitle($this->normalizeTitle($title))
            ->setContent($this->normalizeContent($content))
            ->setKeywords($this->normalizeKeywords($keywords))
            ->setSlug($this->generateUniqueSlug($title))
            ->setIsActive($isActive)
            ->setShowTitle($showTitle)
            ->setShowBreadcrumb($showBreadcrumb)
            ->setCreatedBy($createdBy);

        $this->assertPagePayloadIsValid($page->getTitle() ?? '', $page->getContent() ?? '');

        $this->em->persist($page);
        $this->em->flush();

        $this->invalidatePageCatalog();

        return $page;
    }

    public function updatePage(
        Page $page,
        string $title,
        string $content,
        string $keywords = '',
        bool $isActive = true,
        bool $showTitle = true,
        bool $showBreadcrumb = true,
    ): Page {
        $normalizedTitle = $this->normalizeTitle($title);
        $normalizedContent = $this->normalizeContent($content);
        $this->assertPagePayloadIsValid($normalizedTitle, $normalizedContent);

        $page
            ->setTitle($normalizedTitle)
            ->setContent($normalizedContent)
            ->setKeywords($this->normalizeKeywords($keywords))
            ->setIsActive($isActive)
            ->setShowTitle($showTitle)
            ->setShowBreadcrumb($showBreadcrumb);

        $this->em->flush();
        $this->invalidatePageCatalog();

        return $page;
    }

    public function duplicatePage(Page $page, ?Utilisateur $createdBy = null): Page
    {
        $baseTitle = trim((string) $page->getTitle());
        $duplicateTitle = ($baseTitle !== '' ? $baseTitle : 'Nouvelle page') . ' (copie)';

        return $this->createPage(
            $duplicateTitle,
            (string) $page->getContent(),
            (string) ($page->getKeywords() ?? ''),
            $createdBy,
            $page->isActive(),
            $page->isShowTitle(),
            $page->isShowBreadcrumb(),
        );
    }

    public function deletePage(Page $page): void
    {
        $pagePath = $this->buildManagedPagePath($page);

        $this->menuConfigService->removePageFromAllMenus($pagePath);
        $this->deleteStoredPageConfiguration($pagePath);

        $this->em->remove($page);
        $this->em->flush();

        $this->invalidatePageCatalog();
    }

    public function setPageActive(Page $page, bool $isActive): Page
    {
        $page->setIsActive($isActive);
        $this->em->flush();

        $this->invalidatePageCatalog();

        return $page;
    }

    public function getPublicUrl(Page $page): string
    {
        return $this->urlGenerator->generate(self::PUBLIC_ROUTE, [
            'slug' => (string) $page->getSlug(),
        ]);
    }

    public function uploadEditorAsset(UploadedFile $file, string $kind = 'file'): array
    {
        $upload = $this->fileUploadService->uploadEditorAsset($file, $kind);

        return [
            'success' => true,
            'url' => $this->buildPublicAssetUrl($this->fileUploadService->resolvePublicPath((string) ($upload['path'] ?? ''))),
            'name' => (string) ($upload['name'] ?? 'fichier'),
            'mime' => (string) ($upload['mime'] ?? ''),
            'size' => (int) ($upload['size'] ?? 0),
        ];
    }

    public function repairStoredContent(Page $page): bool
    {
        $currentContent = (string) ($page->getContent() ?? '');
        $normalizedContent = $this->normalizeContent($currentContent);
        if ($normalizedContent === $currentContent) {
            return false;
        }

        $page->setContent($normalizedContent);
        $this->em->flush();

        return true;
    }

    private function invalidatePageCatalog(): void
    {
        $this->pageDisplayService->invalidateConfigurablePagesCache();
    }

    private function assertPagePayloadIsValid(string $title, string $content): void
    {
        if ($title === '') {
            throw new \InvalidArgumentException('Le titre est obligatoire.');
        }

        $plainText = html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (trim($plainText) === '') {
            throw new \InvalidArgumentException('Le contenu est obligatoire.');
        }
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim(strip_tags($title));

        return mb_substr($title, 0, 255, 'UTF-8');
    }

    private function normalizeContent(string $content): string
    {
        $content = trim($content);
        if ($content === '') {
            return '';
        }

        $content = $this->normalizeMediaReferences($content);

        return $this->normalizeImageSizing($content);
    }

    private function normalizeKeywords(string $keywords): ?string
    {
        $keywords = trim(strip_tags($keywords));
        if ($keywords === '') {
            return null;
        }

        return mb_substr($keywords, 0, 500, 'UTF-8');
    }

    private function generateUniqueSlug(string $title): string
    {
        $base = strtolower($this->slugger->slug($title !== '' ? $title : 'page')->toString());
        $base = trim($base, '-');
        if ($base === '') {
            $base = 'page';
        }

        $candidate = $base;
        $suffix = 2;

        while ($this->pageRepository->slugExists($candidate)) {
            $candidate = $base . '-' . $suffix;
            ++$suffix;
        }

        return $candidate;
    }

    private function deleteStoredPageConfiguration(string $pagePath): void
    {
        $this->em->createQuery('DELETE FROM App\Entity\Permission p WHERE p.pagePath = :pagePath')
            ->setParameter('pagePath', $pagePath)
            ->execute();
        $this->em->createQuery('DELETE FROM App\Entity\PageTitle p WHERE p.pagePath = :pagePath')
            ->setParameter('pagePath', $pagePath)
            ->execute();
        $this->em->createQuery('DELETE FROM App\Entity\PageIcon p WHERE p.pagePath = :pagePath')
            ->setParameter('pagePath', $pagePath)
            ->execute();
    }

    private function normalizeMediaReferences(string $content): string
    {
        return preg_replace_callback('/\b(src|href)\s*=\s*(["\'])([^"\']+)\2/i', function (array $matches): string {
            $attribute = (string) ($matches[1] ?? '');
            $quote = (string) ($matches[2] ?? '"');
            $url = $this->normalizeMediaUrl((string) ($matches[3] ?? ''));

            return $attribute . '=' . $quote . $url . $quote;
        }, $content) ?? $content;
    }

    private function normalizeMediaUrl(string $url): string
    {
        $url = trim(str_replace('\\', '/', $url));
        if ($url === '' || preg_match('#^(?:https?:)?//#i', $url) || preg_match('#^(?:mailto:|tel:|data:|javascript:|#)#i', $url)) {
            return $url;
        }

        $parts = parse_url($url);
        if ($parts === false) {
            return $url;
        }

        $path = str_replace('\\', '/', (string) ($parts['path'] ?? $url));
        $uploadPath = $this->extractUploadPath($path);
        if ($uploadPath === null) {
            return $url;
        }

        $normalizedUrl = $this->buildPublicAssetUrl($uploadPath);
        if (isset($parts['query']) && $parts['query'] !== '') {
            $normalizedUrl .= '?' . $parts['query'];
        }
        if (isset($parts['fragment']) && $parts['fragment'] !== '') {
            $normalizedUrl .= '#' . $parts['fragment'];
        }

        return $normalizedUrl;
    }

    private function normalizeImageSizing(string $content): string
    {
        return preg_replace_callback('/<img\b[^>]*>/i', function (array $matches): string {
            $tag = (string) ($matches[0] ?? '');
            if ($tag === '') {
                return $tag;
            }

            $width = $this->extractNumericHtmlAttribute($tag, 'width');
            $height = $this->extractNumericHtmlAttribute($tag, 'height');
            $style = $this->extractHtmlAttribute($tag, 'style') ?? '';
            $normalizedStyle = $this->buildNormalizedImageStyle($style, $width, $height);

            return $this->upsertHtmlAttribute($tag, 'style', $normalizedStyle);
        }, $content) ?? $content;
    }

    private function extractHtmlAttribute(string $tag, string $attribute): ?string
    {
        $pattern = sprintf('/\b%s\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s>]+))/i', preg_quote($attribute, '/'));
        if (!preg_match($pattern, $tag, $matches)) {
            return null;
        }

        foreach (array_slice($matches, 1) as $value) {
            if ($value !== '' && $value !== null) {
                return html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return '';
    }

    private function extractNumericHtmlAttribute(string $tag, string $attribute): ?int
    {
        $value = $this->extractHtmlAttribute($tag, $attribute);
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if ($value === '' || !is_numeric($value)) {
            return null;
        }

        $number = (int) round((float) $value);

        return $number > 0 ? $number : null;
    }

    private function buildNormalizedImageStyle(string $style, ?int $width, ?int $height): string
    {
        $declarations = [];

        foreach (explode(';', $style) as $declaration) {
            $declaration = trim($declaration);
            if ($declaration === '' || !str_contains($declaration, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $declaration, 2);
            $property = strtolower(trim($property));
            $value = trim($value);
            if ($property === '' || $value === '') {
                continue;
            }

            $declarations[$property] = $value;
        }

        if ($width !== null) {
            $declarations['width'] = sprintf('min(100%%, %dpx) !important', $width);
        } elseif (isset($declarations['width'])) {
            $declarations['width'] = $this->ensureImportantStyleValue($declarations['width']);
        } else {
            $declarations['width'] = 'auto !important';
        }

        $declarations['max-width'] = '100% !important';

        if ($width !== null && $height !== null) {
            $declarations['height'] = 'auto !important';
            $declarations['aspect-ratio'] = sprintf('%d / %d', $width, $height);
        } elseif (isset($declarations['height'])) {
            $declarations['height'] = $this->ensureImportantStyleValue($declarations['height']);
        } else {
            $declarations['height'] = 'auto !important';
        }

        $normalizedDeclarations = [];
        foreach ($declarations as $property => $value) {
            $normalizedDeclarations[] = $property . ': ' . trim($value);
        }

        return implode('; ', $normalizedDeclarations);
    }

    private function ensureImportantStyleValue(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return $value;
        }

        return preg_match('/!\s*important$/i', $value) ? $value : $value . ' !important';
    }

    private function upsertHtmlAttribute(string $tag, string $attribute, string $value): string
    {
        $escapedValue = htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pattern = sprintf('/\s+\b%s\s*=\s*(?:"[^"]*"|\'[^\']*\'|[^\s>]+)/i', preg_quote($attribute, '/'));

        if (preg_match($pattern, $tag) === 1) {
            return preg_replace($pattern, sprintf(' %s="%s"', $attribute, $escapedValue), $tag, 1) ?? $tag;
        }

        return preg_replace('/\s*\/?>$/', sprintf(' %s="%s"$0', $attribute, $escapedValue), $tag, 1) ?? $tag;
    }

    private function extractUploadPath(string $path): ?string
    {
        $path = ltrim($path, '/');
        if ($path === '') {
            return null;
        }

        if (preg_match('#(?:^|/)(uploads/.*)$#i', $path, $matches)) {
            return (string) ($matches[1] ?? '');
        }

        if (preg_match('#(?:^|/)(public/uploads/.*)$#i', $path, $matches)) {
            return preg_replace('#^public/#i', '', (string) ($matches[1] ?? '')) ?: null;
        }

        return null;
    }

    private function buildPublicAssetUrl(string $path): string
    {
        $path = '/' . ltrim(str_replace('\\', '/', $path), '/');
        $basePath = rtrim((string) ($this->requestStack->getCurrentRequest()?->getBasePath() ?? ''), '/');

        return $basePath !== '' ? $basePath . $path : $path;
    }
}
