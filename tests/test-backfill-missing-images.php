<?php

class Test_Backfill_Missing_Images extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		$this->image_replacer = new Backfill_Missing_Images\Image_Replacer;
	}

	public function test_get_images_from_string() {

		$upload_dir = wp_upload_dir();
		$url = $upload_dir['url'] . '/image-300x200.png';

		$string = '<img class="wp-image-123" src="' . $url . '" />';
		$images = $this->image_replacer->get_images_from_string( $string );

		$this->assertEquals( array( array(
			'id'   => '123',
			'url'  => $url,
			'size' => array( 300, 200 ),
			'original_path' => $upload_dir['path'] . '/image.png'
		) ), $images );
	}

	public function test_get_images_from_string_src_attr_first() {

		$upload_dir = wp_upload_dir();
		$url = $upload_dir['url'] . '/image-300x200.png';

		$string = '<img src="' . $url . '" class="wp-image-123" />';
		$images = $this->image_replacer->get_images_from_string( $string );

		$this->assertEquals( array( array(
			'id'   => '123',
			'url'  => $url,
			'size' => array( 300, 200 ),
			'original_path' => $upload_dir['path'] . '/image.png'
		) ), $images );
	}

	public function test_get_images_from_string_additional_attrs() {

		$upload_dir = wp_upload_dir();
		$url = $upload_dir['url'] . '/image-300x200.png';

		$string = '<img alt="Hello!" src="' . $url . '" rel="foo" class="wp-image-123" />';
		$images = $this->image_replacer->get_images_from_string( $string );

		$this->assertEquals( array( array(
			'id'   => '123',
			'url'  => $url,
			'size' => array( 300, 200 ),
			'original_path' => $upload_dir['path'] . '/image.png'
		) ), $images );
	}

	public function test_get_images_from_string_space_in_class() {

		$upload_dir = wp_upload_dir();
		$url = $upload_dir['url'] . '/image-300x200.png';

		$string = '<img src="' . $url . '" class=" wp-image-123 " />';
		$images = $this->image_replacer->get_images_from_string( $string );

		$this->assertEquals( array( array(
			'id'   => '123',
			'url'  => $url,
			'size' => array( 300, 200 ),
			'original_path' => $upload_dir['path'] . '/image.png'
		) ), $images );
	}

	public function test_get_images_from_string_additoinal_classes() {

		$upload_dir = wp_upload_dir();
		$url = $upload_dir['url'] . '/image-300x200.png';

		$string = '<img src="' . $url . '" class="lazyload wp-image-123 full-wdith " />';
		$images = $this->image_replacer->get_images_from_string( $string );

		$this->assertEquals( array( array(
			'id'   => '123',
			'url'  => $url,
			'size' => array( 300, 200 ),
			'original_path' => $upload_dir['path'] . '/image.png'
		) ), $images );
	}

	public function test_get_images_from_string_utf8_chars() {

		$upload_dir = wp_upload_dir();
		$url = $upload_dir['url'] . '/juvenil-blåmeis-326x244.jpg';

		$string = '<img class="size-medium wp-image-22781" title="juvenil-blåmeis" src="' . $url . '" alt="" width="326" height="244" />';
		$images = $this->image_replacer->get_images_from_string( $string );

		$this->assertEquals( array( array(
			'id'   => '22781',
			'url'  => $url,
			'size' => array( 326, 244 ),
			'original_path' => $upload_dir['path'] . '/juvenil-blåmeis.jpg'
		) ), $images );
	}


	public function test_is_image_missing() {

		$upload_dir = wp_upload_dir();
		$post       = $this->factory->post->create();
		$file       = dirname( __FILE__ ) . '/inc/image.jpg';
		$attachment = $this->factory->attachment->create_object( $file, $post, array( 'post_mime_type' => 'image/jpeg' ) );

		$is_missing = $this->image_replacer->is_image_missing( array(
			'id'   => $attachment,
			'url'  => $upload_dir['url'] . '/missing.png',
			'size' => array( 200, 400 )
		));

		$this->assertTrue( $is_missing );
	}

	public function test_is_missing_image_no_attachment_id() {
		$upload_dir = wp_upload_dir();
		$post       = $this->factory->post->create();
		$file       = dirname( __FILE__ ) . '/inc/image.jpg';
		$attachment = $this->factory->attachment->create_object( $file, $post, array( 'post_mime_type' => 'image/jpeg' ) );

		$is_missing = $this->image_replacer->is_image_missing( array(
			'id'   => 9999999,
			'url'  => $upload_dir['url'] . '/missing.png',
			'size' => array( 200, 400 )
		));

		$this->assertTrue( $is_missing );
	}

	public function test_is_image_missing_false() {

		$post       = $this->factory->post->create();
		$file       = dirname( __FILE__ ) . '/inc/image.jpg';

		add_image_size( 'small', 100, 100, true );
		$attachment = $this->factory->attachment->create_object( $file, $post, array( 'post_mime_type' => 'image/jpeg' ) );

		$is_missing = $this->image_replacer->is_image_missing( array(
			'id'   => $attachment,
			'url'  => wp_get_attachment_image_src( $attachment, 'small')[0],
			'size' => array( 100, 100 )
		));

		$this->assertFalse( $is_missing );
	}

	public function test_get_external_attachments_from_string() {

		$post       = $this->factory->post->create();
		$file       = dirname( __FILE__ ) . '/inc/image.jpg';

		add_image_size( 'small', 100, 100, true );
		$attachment = $this->factory->attachment->create_object( $file, $post, array( 'post_mime_type' => 'image/jpeg' ) );

		$images = $this->image_replacer->get_external_attachments_from_string( 'something <img class="wp-image-' . $attachment . '" src="http://something.wordpress.com/files/123.jpg" width="100" height="120" alt="foo bar" />' );
		$this->assertEquals( array( array( 'url' => 'http://something.wordpress.com/files/123.jpg', 'id' => $attachment, 'size' => array( 100, 120 ) ) ), $images );
	}
}