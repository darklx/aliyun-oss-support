<?php

namespace OSS\WP;

class UrlHelper
{

    /**
     * 修改为静态方法，这样外部（其它插件），可以调用里面的方法
     */
    public static function add_filter()
    {
        add_filter('upload_dir', array(UrlHelper::class, 'resetUploadBaseUrl'), 30);

        if (Config::$enableImgService) {
            add_filter('wp_get_attachment_metadata', array(UrlHelper::class, 'replaceImgMeta'), 900);

            if (Config::$enableImgStyle) {
                add_filter('wp_get_attachment_url', array(UrlHelper::class, 'replaceImgUrl'), 30, 2);
                add_filter('wp_calculate_image_srcset', array(UrlHelper::class, 'replaceImgSrcset'), 900);
            }
        }
    }

    /**
     * 修改从数据库中取出的图片信息，以使用 Aliyun 的图片服务
     * 仅在开启图片服务时启用
     *
     * @param $data
     * @return mixed
     */
    public static function replaceImgMeta($data)
    {
        if (empty($data['sizes']))
            return $data;

        $pathinfo = pathinfo($data['file']);
        // get file name with extensions
        $filename = $pathinfo['basename'];
        //$filename = basename($data['file']);

        // fix On Debug mode, PHP Notice:  Only variables should be passed by reference
        $suffix = $pathinfo['extension'];//pathinfo($filename, PATHINFO_EXTENSION); // extension name without dot
        //$suffix = end(explode('.', $filename));

        if (Config::$enableImgStyle && $suffix != 'gif') {
            foreach (array('thumbnail', 'post-thumbnail', 'medium', 'medium_large', 'large', 'full') as $style) {
                if (isset($data['sizes'][$style]))
                    $data['sizes'][$style]['file'] = self::aliImageStyle($filename, $style);
            }
        } else {
            foreach ($data['sizes'] as $size => $info)
                $data['sizes'][$size]['file'] = self::aliImageResize($filename, $info['height'], $info['width']);
        }

        return $data;
    }

    /**
     * 重置图片链接, 仅在开启图片服务时启用
     *
     * @param $url
     * @param $post_id
     * @return mixed
     */
    public static function replaceImgUrl($url, $post_id)
    {
        if (wp_attachment_is_image($post_id))
            $url = self::aliImageStyle($url, 'full');
        return $url;
    }

    /**
     * 重置 Srcset 中原图链接, 仅在开启图片服务时启用
     *
     * @param $sources
     * @return mixed
     */
    public static function replaceImgSrcset($sources)
    {
        foreach ($sources as $k => $source) {
            if (false === strpos($source['url'], "x-oss-process="))
                $sources[$k]['url'] = self:: aliImageStyle($source['url'], 'full');
        }
        return $sources;
    }

    /**
     * 设置 upload_url_path，使用外部存储OSS
     *
     * @param $uploads
     * @return mixed
     */
    public static function resetUploadBaseUrl($uploads)
    {
        if (Config::$staticHost) {
            $baseUrl = rtrim(Config::$staticHost . Config::$storePath, '/');
            $uploads['baseurl'] = $baseUrl;
        }
        return $uploads;
    }

    public static function aliImageResize($file, $height, $width)
    {
        return "{$file}?x-oss-process=image/resize,m_fill,h_{$height},w_{$width}";
    }

    public static function aliImageStyle($file, $style)
    {
        $suffix = pathinfo($file, PATHINFO_EXTENSION);
        // On Debug mode, PHP Notice:  Only variables should be passed by reference
        //$suffix = end(explode('.', $file));
        
        // 如果 $style 传入空，则不生成带 oss style 的参数，因为我发现，oss style 即使为 full，返回的图片却比实际（不带参数的）原图文件尺寸要大不少！
        // 这里其实还可以多处理一个地方，就是如果 $style=="full" 且 oss 没有开启原图片保护的情况下，则直接返回
        // 不带 x-oss-process 参数的 URL，减少不必要的 OSS 的流量费用消耗。
        return $suffix == 'gif' ? $file : empty($style) ?$file: "{$file}?x-oss-process=style%2F{$style}" ;
    }

}