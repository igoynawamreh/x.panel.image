<?php namespace _\lot\x\panel\image\page;

function fields($_) {
    extract($GLOBALS, \EXTR_SKIP);
    $has_image_extension = !empty($state->x->image);
    $image = (new \Page($_['f']))['image'];
    $_['lot']['desk']['lot']['form']['lot'][1]['lot']['tabs']['lot']['image'] = [
        'lot' => [
            'fields' => [
                'type' => 'fields',
                'lot' => [
                    'view' => [
                        'title' => 'Image',
                        'type' => 'field',
                        'content' => '<img alt="' . \basename($image) . '" src="' . $image . '?v=' . filemtime($_['f']) . '"><input name="image[link]" type="hidden" value="' . $image . '">',
                        'hidden' => 's' === $_['task'] || !$image,
                        'stack' => 9.9
                    ],
                    'image' => 'g' === $_['task'] && $image ? [
                        'title' => "",
                        'type' => 'items',
                        'name' => 'image',
                        'lot' => [
                            'let' => [
                                'title' => 'Delete',
                                'value' => 1
                            ]
                        ],
                        'tags' => ['mt:0'],
                        'stack' => 10
                    ] : [
                        'title' => 'File',
                        // Unless it’s prefixed by `blob`, `data`, `file` or `page`,
                        // this field data will not be stored t a file automatically
                        'type' => 'blob',
                        'name' => 'image[blob]',
                        'stack' => 10
                    ],
                    'rect' => [
                        'title' => 'Resize',
                        // Unless it’s prefixed by `blob`, `data`, `file` or `page`,
                        // this field data will not be stored to a file automatically
                        'name' => 'image[rect]',
                        'description' => $has_image_extension ? 'Set maximum width and height of the image.' : 'This feature requires you to install the <a href="https://github.com/mecha-cms/x.image" target="_blank">image</a> extension.',
                        'type' => 'combo',
                        'active' => $has_image_extension,
                        'lot' => [
                            "" => 'None',
                            '1024x538' => \S . "1024 \u{00D7} 538" . \S,
                            // '250x250' => \S . "250 \u{00D7} 250" . \S
                        ],
                        'hidden' => 'g' === $_['task'] && $image,
                        'stack' => 20
                    ]
                ],
                'stack' => 10
            ]
        ],
        'stack' => 11
    ];
    return $_;
}

function requests($_, $lot) {
    // Not a `POST` request, abort!
    if ('POST' !== $_SERVER['REQUEST_METHOD']) {
        return $_;
    }
    // Abort by previous hook’s return value if any
    if (!empty($_['alert']['error'])) {
        return $_;
    }
    extract($GLOBALS, \EXTR_SKIP);
    $image = $lot['image'] ?? [];
    $link = null; // Prepare page’s `image` data
    // Delete or update
    if (!empty($image['link'])) {
        // Delete
        if (!empty($image['let'])) {
            $path = \strtr($image['link'], [
                $url . "" => "",
                '../' => "", // Prevent directory traversal attack
                '/' => \DS
            ]);
            if (\is_file($f = \ROOT . $path)) {
                // Just to be sure
                if (false === \strpos(',gif,jpeg,jpg,png,', ',' . \pathinfo($f, \PATHINFO_EXTENSION) . ',')) {
                    $_['alert']['error'][] = ['Could not delete %s because it is likely not an image.', '<code>' . \basename($path) . '</code>'];
                } else if (0 !== strpos(mime_content_type($f), 'image/')) {
                    $_['alert']['error'][] = ['Could not delete %s because it is likely not an image.', '<code>' . \basename($path) . '</code>'];
                } else {
                    \unlink($f);
                    $link = false;
                    $_['alert']['success'][] = ['%s %s successfully deleted.', ['Image', '<code>' . \strtr($f, [\ROOT => '.']) . '</code>']];
                }
            }
        // Update
        } else {
            $link = $image['link'];
        }
    // Upload
    } else if (!empty($image['blob']['name'])) {
        $x = \pathinfo($image['blob']['name'] = $name = \To::file($image['blob']['name']), \PATHINFO_EXTENSION);
        $folder = \LOT . \DS . 'asset' . \DS . $x . (1 === $user['status'] ? "" : \DS . $user->key);
        $f = '<code>' . \strtr($folder . \DS . $name, [\ROOT => '.']) . '</code>'; // File name preview
        // Check for image file extension
        if (false === \strpos(',gif,jpeg,jpg,png,', ',' . $x . ',')) {
            $_['alert']['error'][] = ['Please upload an image file.'];
        // Check for image file type
        } else if (0 !== \strpos($image['blob']['type'], 'image/')) {
            $_['alert']['error'][] = ['Please upload an image file.'];
        // Check for image file size
        } else if (0 /* image too small */) {
            // $_['alert']['error'][] = ['Minimum file size allowed to upload is %s.', '<code>' . \File::sizer($test_size) . '</code>'];
        } else if (0 /* image too large */) {
            // $_['alert']['error'][] = ['Maximum file size allowed to upload is %s.', '<code>' . \File::sizer($test_size) . '</code>'];
        } else {
            // Uploading...
            $response = \File::push($image['blob'], $folder);
            if (false === $response) {
                $_['alert']['info'][] = ['%s %s already exists.', ['Image', $f]];
                $link = \To::URL($folder . \DS . $name);
            // Check for error code
            } else if (\is_int($response)) {
                $_['alert']['error'][] = '#blob:' . $response;
            } else {
                // Resize image
                if (isset($state->x->image) && !empty($image['rect']) && \preg_match('/^(\d+)x(\d+)$/', $image['rect'], $m)) {
                    $blob = new \Image($folder . \DS . $name);
                    $blob->crop((int) $m[1], (int) $m[2]);
                    $blob->let(); // Delete current image
                    $blob->store($blob->path); // Save as current image with the updated size
                }
                $_['alert']['success'][] = ['%s %s successfully uploaded.', ['Image', $f]];
                $link = \To::URL($response);
            }
            // Remove temporary form data
            \Post::let('image');
        }
    }
    if (isset($link)) {
        $data = \From::page(\file_get_contents($_['f']));
        if (false !== $link) {
            $data['image'] = $link;
        } else {
            unset($data['image']);
        }
        \file_put_contents($_['f'], \To::page($data));
    }
    return $_;
}

\Hook::set('_', __NAMESPACE__ . "\\fields");

\Hook::set([
    'do.page.get',
    'do.page.set'
], __NAMESPACE__ . "\\requests", 20);
