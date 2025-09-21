<?php
/**
 * Plugin Name: RSS Importer – Remove Duplicate Featured Image
 * Description: Removes the first inline image from imported posts if it duplicates the featured image.
 * Version:     1.0.0
 * Author:      Erik Skogh
 * License:     GPL-2.0-or-later
 *
 * Watches for newly inserted/updated posts and removes the topmost inline image
 * if it is the same asset as the post thumbnail.
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('RSSFTI_Remove_Duplicate_Featured_Image')) {
    final class RSSFTI_Remove_Duplicate_Featured_Image {

        public static function init(): void {
            add_action('wp_after_insert_post', [__CLASS__, 'maybe_strip_leading_duplicate_img'], 10, 4);
        }

        /**
         * Remove leading inline <img> (or a common wrapper) if it matches the featured image.
         */
        public static function maybe_strip_leading_duplicate_img(int $post_id, WP_Post $post, bool $update, ?WP_Post $post_before): void {
            // Guards
            if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) return;
            if ($post->post_status !== 'publish') return;
            if (!in_array($post->post_type, ['post', 'page'], true)) return;

            $thumb_id = get_post_thumbnail_id($post_id);
            if (!$thumb_id) return;

            $thumb_src = wp_get_attachment_image_src($thumb_id, 'full');
            if (!$thumb_src || empty($thumb_src[0])) return;
            $thumb_url = $thumb_src[0];

            $content = $post->post_content;
            if (!$content || stripos($content, '<img') === false) return;

            // Normalize to compare same image across sizes/params.
            $normalize = static function (string $url): string {
                $url = preg_replace('#\?.*$#', '', $url);
                $path = parse_url($url, PHP_URL_PATH) ?: '';
                $base = basename($path);
                $base = preg_replace('/\.[a-z0-9]{2,5}$/i', '', $base);
                $base = preg_replace('/-\d+x\d+$/', '', $base);
                return strtolower($base);
            };
            $thumb_key = $normalize($thumb_url);

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML('<?xml encoding="utf-8" ?><div id="__wrap__">'.$content.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $wrap = $dom->getElementById('__wrap__');
            if (!$wrap) return;

            // Find first meaningful element node
            $firstEl = null;
            foreach ($wrap->childNodes as $node) {
                if ($node->nodeType === XML_ELEMENT_NODE) { $firstEl = $node; break; }
                if ($node->nodeType === XML_TEXT_NODE && trim($node->textContent) !== '') { return; }
            }
            if (!$firstEl) return;

            // Accept wrappers and bare <img>
            $candidate = $firstEl;
            $tag = strtolower($candidate->nodeName);
            if (!in_array($tag, ['p', 'figure', 'div', 'img', 'a'], true)) return;

            if ($tag === 'a' && $candidate->getElementsByTagName('img')->length) { /* keep <a> wrapper */ }

            $imgNode = null;
            if ($tag === 'img') $imgNode = $candidate;
            else {
                $imgs = $candidate->getElementsByTagName('img');
                if ($imgs->length > 0) $imgNode = $imgs->item(0);
            }
            if (!$imgNode) return;

            $imgSrc = $imgNode->getAttribute('src');
            if (!$imgSrc) return;

            if ($normalize($imgSrc) !== $thumb_key) return;

            // Remove entire block
            if ($candidate->parentNode) $candidate->parentNode->removeChild($candidate);

            // Serialize body back
            $newContent = '';
            foreach ($wrap->childNodes as $child) { $newContent .= $dom->saveHTML($child); }

            // Prevent loops
            remove_action('wp_after_insert_post', [__CLASS__, 'maybe_strip_leading_duplicate_img'], 10);
            wp_update_post(['ID' => $post_id, 'post_content' => $newContent]);
            add_action('wp_after_insert_post', [__CLASS__, 'maybe_strip_leading_duplicate_img'], 10, 4);
        }
    }

    RSSFTI_Remove_Duplicate_Featured_Image::init();
}
