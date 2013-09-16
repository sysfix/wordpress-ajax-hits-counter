<?php
/**
 * Plugin Name: AJAX Hits Counter + Popular Posts Widget
 * Plugin URI: http://romantelychko.com/downloads/wordpress/plugins/ajax-hits-counter.latest.zip
 * Description: Counts page/posts hits via AJAX and display it in admin panel. Ideal for nginx whole-page-caching. Popular Posts Widget included.
 * Version: 0.8.8
 * Author: Roman Telychko
 * Author URI: http://romantelychko.com
*/

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * AJAX_Hits_Counter
 */
class AJAX_Hits_Counter
{
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::init()
	 *
	 * @return      bool
	 */
    public function init()
    {
        if( is_admin() )
        {
            // admin posts table
            add_filter( 'manage_posts_columns',                         array( $this, 'adminTableColumn' ) );
            add_filter( 'manage_posts_custom_column',                   array( $this, 'adminTableRow' ), 10, 2 );
            add_filter( 'manage_edit-post_sortable_columns',            array( $this, 'adminTableSortable' ) );
            add_filter( 'request',                                      array( $this, 'adminTableOrderBy' ) );    
            
            // admin pages table
            add_filter( 'manage_pages_columns',                         array( $this, 'adminTableColumn' ) );
            add_filter( 'manage_pages_custom_column',                   array( $this, 'adminTableRow' ), 10, 2 );
            add_filter( 'manage_edit-page_sortable_columns',            array( $this, 'adminTableSortable' ) );
            
            // remove cached data on every post save & update hits count
            add_action( 'save_post',                                    array( $this, 'adminSave' ) );
            
            // init admin            
            add_action('admin_init',                                    array( $this, 'adminInit' ) );
            
            // register importer
            require_once(ABSPATH.'wp-admin/includes/import.php');
            
            register_importer( 
                __CLASS__.'_Importer',
                'AJAX Hits Counter: Import from WP-PostViews',
                'Imports views count (hits) from plugin <a href="http://wordpress.org/plugins/wp-postviews">WP-PostViews</a> to hits of <a href="http://wordpress.org/plugins/ajax-hits-counter/">AJAX Hits Counter</a>.',
                array( $this, 'adminImporter' )
                );
        }
        else
        {
            // append script to content
            add_filter( 'the_content',                                  array( $this, 'appendScript' ),       100);
        }

        // register AJAX Hits Counter: Popular Posts Widget
        add_action( 'widgets_init',                                     array( $this, 'register' ) );

        // AJAX increment hits init    
        add_action( 'wp_ajax_nopriv_ajax-hits-counter-increment',       array( $this, 'incrementHits' ) );
        add_action( 'wp_ajax_ajax-hits-counter-increment',              array( $this, 'incrementHits' ) );
        
        return true;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::register()
	 *
	 * @return      bool
	 */
    public function register()
    {
        return register_widget( 'AJAX_Hits_Counter_Popular_Posts_Widget' );
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::appendScript()
	 *
	 * @param       string      $content
	 * @return      string
	 */
    public function appendScript( $content )
    {
        global $post;
    
        if( is_single() || is_page() ) 
        {
            $content .=
                '<script type="text/javascript">'.
                    'function ahc_getXmlHttp(){var e;try{e=new ActiveXObject("Msxml2.XMLHTTP")}catch(t){try{e=new ActiveXObject("Microsoft.XMLHTTP")}catch(n){e=false}}if(!e&&typeof XMLHttpRequest!="undefined"){e=new XMLHttpRequest}return e};'.
                    'var ahc_xmlhttp=ahc_getXmlHttp();'.
                    'ahc_xmlhttp.open('.
                        '"GET",'.
                        '"'.admin_url( 'admin-ajax.php' ).
                        '?action=ajax-hits-counter-increment'.
                        '&post_id='.$post->ID.
                        '&t="+(parseInt(new Date().getTime()))+"&r="+(parseInt(Math.random()*100000))'.
                        ');'.
                    'ahc_xmlhttp.send(null);'.
                '</script>';
        }
        
        return $content;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::incrementHits()
	 *
	 * @return      void
	 */
    public function incrementHits()
    {
        if( !isset($_GET['post_id']) || empty($_GET['post_id']) )
        {
            die( '0' );
        }    
        
        $post_id = intval( filter_var( $_GET['post_id'], FILTER_SANITIZE_NUMBER_INT ) );
        
        if( empty($post_id) )
        {
            die( '0' );
        }

        $current_hits = get_post_meta( $post_id, 'hits', true );
        
        if( empty($current_hits) ) 
        {
            $current_hits = 0;
        }
        
        $current_hits++;
            
        update_post_meta( $post_id, 'hits', $current_hits );
        
        die( strval( $current_hits ) );
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::getHits()
	 *
	 * @param       integer     $post_id
	 * @return      integer
	 */
    public function getHits( $post_id )
    {
        $post_id = intval( filter_var( $post_id, FILTER_SANITIZE_NUMBER_INT ) );

        if( empty($post_id) )
        {
            return 0;
        }
        
        $hits = get_post_meta( $post_id, 'hits', true );

        if( empty($hits) ) 
        {
            return 0;
        }
        
        return intval($hits);
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminInit()
	 *
	 * @return      bool
	 */
    public function adminInit()
    {
        global $current_user;

        if( isset($current_user->roles) && !empty($current_user->roles) && in_array( 'administrator', $current_user->roles ) )
        {
            // add meta box
            add_action( 'add_meta_boxes',                               array( $this, 'adminAddMetaBox' ) );            
        }
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminSave()
	 *
	 * @param       integer     $post_id
	 * @return      bool
	 */
    public function adminSave( $post_id )
    {
        // skip for autosave
        if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
        {
            return;
        }

        // update hits count
        if( isset($_POST['post_type']) && in_array( $_POST['post_type'], array( 'post', 'page' ) ) )
        {    
            $hits = ( isset($_POST['hits']) && !empty($_POST['hits']) ? intval( preg_replace( '/[^0-9]/', '', $_POST['hits'] ) ) : 0 );
            
            if( $hits > 0 )
            {
                $hits_exists = get_post_meta( $post_id, 'hits', true );
                
                if( $hits_exists===false )
                {
                    add_post_meta( $post_id, 'hits', $hits, true );
                }
                else
                {
                    update_post_meta( $post_id, 'hits', $hits );
                }
            }
        }
    
        // clear Popular Posts Widget
        $ahc_ppw = new AJAX_Hits_Counter_Popular_Posts_Widget();
        $ahc_ppw->clearCache();
        
        return true;
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableColumn()
	 *
	 * @param       array     $column
	 * @return      array
	 */
    public function adminTableColumn( $column )
    {
        $column['hits'] = 'Hits';    

        return $column;
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableRow()
	 *
	 * @param       string      $column_name
	 * @param       integer     $post_id
	 * @return      string
	 */
    public function adminTableRow( $column_name, $post_id )
    {
        if( $column_name=='hits' )
        {
            $current_hits = get_post_meta( $post_id, 'hits', true );
            
            if( empty($current_hits) ) 
            {
                $current_hits = 0;
            }
            
            echo( $current_hits );
        }
    }
    
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableSortable()
	 *
	 * @param       array       $column
	 * @return      array
	 */
    public function adminTableSortable( $column )
    {
        $column['hits'] = 'hits';    

        return $column;
    }
        
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter::adminTableOrderBy()
	 *
	 * @param       array       $vars
	 * @return      array
	 */
    public function adminTableOrderBy( $vars )
    {
	    if( isset($vars['orderby']) && $vars['orderby']=='hits' ) 
	    {
		    $vars = array_merge( 
        		    $vars, 
        		    array(
			            'meta_key'  => 'hits',
			            'orderby'   => 'meta_value_num'
            		    ) 
        		    );
	    }
     
	    return $vars;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminAddMetaBox()
	 *
	 * @return      bool
	 */
    public function adminAddMetaBox()
    {
        add_meta_box(
            'hits',
            'Hits count',
            array( $this, 'adminAddMetaBoxPrint' ),
            'post',
            'side',
            'default'
            );
            
        add_meta_box(
            'hits',
            'Hits count',
            array( $this, 'adminAddMetaBoxPrint' ),
            'page',
            'side',
            'default'
            );
            
        return true;
    }
    
    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminAddMetaBoxPrint()
	 *
	 * @param       string          $post
	 * @param       string          $metabox	 
	 * @return      void
	 */
    public function adminAddMetaBoxPrint( $post, $metabox ) 
    {
        wp_nonce_field( plugin_basename( __FILE__ ), 'ajax_hits_counter_nonce' );
        
        $hits = get_post_meta( $post->ID, 'hits', true );

        echo( 
            '<label for="hits">Hits count</label>&nbsp;&nbsp;'.
            '<input type="text" name="hits" id="hits" value="'.( !empty($hits) ? $hits : '0' ).'" />' 
            );
    }

    ///////////////////////////////////////////////////////////////////////////
    
	/**
	 * AJAX_Hits_Counter::adminImporter()
	 *
	 * @return      html
	 */
    public function adminImporter() 
    {
        ///////////////////////////////////////////////////////////////////////
	    
	    $html = 
		    '<div class="wrap">'.
		        '<h2>AJAX Hits Counter: Import from WP-PostViews</h2>'.
		        '<div class="clear"></div>';
    
        ///////////////////////////////////////////////////////////////////////
    
	    global $wpdb;
	    
        ///////////////////////////////////////////////////////////////////////
        
        if( !isset($_POST['start']) || empty($_POST['start']) )
        {
            ///////////////////////////////////////////////////////////////////

            $q = '
                SELECT
	                count(post_id) as c
                FROM
                    '.$wpdb->postmeta.'
                WHERE	
                    meta_key = \'views\'';

            $total = $wpdb->get_var($q);

            ///////////////////////////////////////////////////////////////////
            
            if( empty($total) )
            {
	            $html .= 
	                '<p>We found <strong>no items</strong> to import from WP-PostViews plugin.</p>'.
	                '<p>Have I hice day ;-)</p>';
            }
            else
            {
                $html .= 
                    '<p>We found <strong>'.$total.' items</strong> to import from WP-PostViews plugin.</p>'.
                    '<p>To start import please click "Start procession" button.</p>'.
                    '<form method="post">'.
                        wp_nonce_field( plugin_basename( __FILE__ ), 'ajax_hits_counter_nonce', true, false ).
                        '<input type="submit" value="Start procession" class="button" name="start" />'.
                    '</form>';
            }
            
            ///////////////////////////////////////////////////////////////////
        }
        else
        {
            ///////////////////////////////////////////////////////////////////
            
            $q = '
                SELECT
	                post_id,
	                meta_value      as views
                FROM
                    '.$wpdb->postmeta.'
                WHERE	
                    meta_key = \'views\'';

            $results = $wpdb->get_results($q);

            ///////////////////////////////////////////////////////////////////

            if( !empty($results) )
            {
                $status = array(
                    'total'         => count($results),
                    'inserted'      => 0,
                    'updated'       => 0,
                    'skipped'       => 0,
                    );
            
                foreach( $results as $r )
                {                            
                    $hits = get_post_meta( $r->post_id, 'hits', true );

                    if( $hits===false )
                    {
                        add_post_meta( $r->post_id, 'hits', $r->views, true );
                        
                        $status['inserted']++;
                    }
                    else
                    {
                        if( $hits < $r->views )
                        {
                            update_post_meta( $r->post_id, 'hits', $r->views );
                            
                            $status['updated']++;
                        }
                        else
                        {
                            $status['skipped']++;                    
                        }
                    }
                }
                
	            $html .= 
	                '<p>Imported <strong>'.$status['total'].' items</strong> (inserted: <strong>'.$status['inserted'].'</strong>, updated: <strong>'.$status['updated'].'</strong>, skipped: <strong>'.$status['skipped'].'</strong>)</p>'.
	                '<p>Thank you for choosing our plugin.</p>';
            }
            else
            {
	            $html .= 
	                '<p>We found <strong>no items</strong> to import from WP-PostViews plugin.</p>'.
	                '<p>Have I hice day ;-)</p>';
            }
            
            ///////////////////////////////////////////////////////////////////
        }

        ///////////////////////////////////////////////////////////////////////
        
        $html .= 
	        '</div>';
        
        die( $html );

        ///////////////////////////////////////////////////////////////////////
    }

    ///////////////////////////////////////////////////////////////////////////
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

/**
 * AJAX_Hits_Counter_Popular_Posts_Widget
 */
class AJAX_Hits_Counter_Popular_Posts_Widget extends WP_Widget 
{
    ///////////////////////////////////////////////////////////////////////////
    
    protected $defaults = array(
        'widget_id'                 => 'ajax_hits_counter_popular_posts_widget',
        'sorting_algorithm'         => 1,               // hits only
        'sorting_coefficient_n'     => 1,               // sorting coefficient of hits
        'sorting_coefficient_k'     => 10,              // sorting coefficient of comments
        'count'                     => 5,               // limit
        'cache_lifetime'            => 3600,            // 1 hour as default
        'date_range'                => 7,               // all time (no time limit)
        'one_element_html'          => "<span class=\"entry-content\">\n  <a href=\"{permalink}\" title=\"{post_title}\">{post_title} ({post_hits})</a>\n</span>",
        'post_type'                 => 1,               // posts only
        'post_category'             => -1,              // any
        'post_category_exclude'     => -3,              // none
        'post_categories_separator' => ', ',
        'post_date_format'          => 'd.m.Y',
        );

    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::__construct()
	 * ( Register widget with WordPress )
	 *
	 * @return      void
	 */
	public function __construct() 
	{	
		///////////////////////////////////////////////////////////////////////
		
		parent::__construct(
	 		$this->defaults['widget_id'],
			'AJAX Hits Counter: Popular Posts',
			array(
			    'description'   => 'Displays popular posts/pages counted by AJAX Hits Counter.', 
			    'classname'     => $this->defaults['widget_id'],
			    ),
		    array(
			    'width'     => 800,
			    'height'    => 600,
		    )
		);

		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::widget()
	 * ( Front-end display of widget. )
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param       array       $args               Widget arguments.
	 * @param       array       $instance           Saved values from database.
     * @return      html
	 */
	public function widget( $args, $instance ) 
	{	
		///////////////////////////////////////////////////////////////////////

	    // args
	    $args = array_merge( $this->defaults, $args );
		
        // cache key
        $cache_key = 'ajax_hits_counter_'.dechex(crc32( $args['widget_id'] ));

        // try to get cached data from transient cache
        $cache = get_transient( $cache_key );

        if( !is_array($cache) && !empty($cache) )
        #if( false )
        {
            // cache exists, return cached data
            echo( $cache );            
            return true;
        }
        
        // get popular posts
	    $popular_posts = $this->getPopularPosts( $instance );
	    
		if( empty($popular_posts) )
		{
		    return false;
		}
		
		$title = apply_filters( 'widget_title', $instance['title'] );
 
        $output =
            ( isset($instance['custom_css']) && strlen($instance['custom_css'])>5 ? '<style type="text/css">'.$instance['custom_css'].'</style>' : '' ).
            $args['before_widget'];

		if( !empty( $title ) )
		{
			$output .= $args['before_title'].$title.$args['after_title'];
		}
		
		$output .= $this->getHTML( $popular_posts, $instance );

		$output .= $args['after_widget'];
	
        // store result to cache
        set_transient( $cache_key, $output, $instance['cache_lifetime'] );	
		
		echo( $output );
		
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::getPopularPosts()
	 * ( Returns Popular Posts )
	 *
	 * @param       array       $args
	 * @return      array
	 */
	protected function getPopularPosts( $args = array() )
	{
		///////////////////////////////////////////////////////////////////////
	
	    global $wpdb;
	    
	    if( isset($args['sorting_algorithm']) )
	    {
            switch( $args['sorting_algorithm'] )
            {
                case 1:         // hits only
                default:
                    $sql_sorting_algorithm = '( m.meta_value + 0 ) DESC,';
                    break;
                
                case 2:         // comments only
                    $sql_sorting_algorithm = '( p.comment_count + 0 ) DESC,';
                    break;
                
                case 3:         // N * hits + K * comments
                    $sql_sorting_algorithm = '( ( ( m.meta_value + 0 ) * '.$args['sorting_coefficient_n'].' ) + ( p.comment_count + 0 ) * '.$args['sorting_coefficient_k'].' ) DESC,';
                    break;
            }
        }
        else
        {
            $sql_sorting_algorithm = '( m.meta_value + 0 ) DESC,';
        }

		///////////////////////////////////////////////////////////////////////

        // SELECT, FROM, WHERE
        $q = '
            SELECT
	            DISTINCT p.ID,
	            p.post_title,
	            p.post_content,
	            p.post_author,
	            p.post_date,
	            m.meta_value        as post_hits,
	            p.comment_count     as post_comments_count
            FROM
	            '.$wpdb->posts.' p
            JOIN
                '.$wpdb->postmeta.' m ON ( p.ID = m.post_id )
            WHERE	
	            p.post_date_gmt < \''.date( 'Y-m-d H:i:s' ).'\'';

        // date range
        if( isset($args['date_range']) && $args['date_range']<7 )
        {
            switch( $args['date_range'] )
            {
                case 1:
                    $temp_post_date_shift = '-1 day';
                    break;
                    
                case 2:
                    $temp_post_date_shift = '-1 week';
                    break;
                    
                case 3:
                    $temp_post_date_shift = '-1 month';
                    break;
                    
                case 4:
                    $temp_post_date_shift = '-3 months';
                    break;
                    
                case 5:
                    $temp_post_date_shift = '-6 months';
                    break;
                   
                case 6:
                    $temp_post_date_shift = '-1 year';
                    break;
                    
                default:
                    $temp_post_date_shift = false;
            }
            
            if( !empty($temp_post_date_shift) )
            {        
                $q .= '
                    AND
                    p.post_date_gmt >= \''.date( 'Y-m-d H:i:s', strtotime( $temp_post_date_shift ) ).'\'';
            }
        }
        
        // posts status & meta key = hits
        $q .= '
            AND
            p.post_status = \'publish\'
            AND
            m.meta_key = \'hits\'';

        // post type
        if( isset($args['post_type']) )
        {
            switch($args['post_type'])
            {
                case 0:
                    // all types
                    break;
                    
                case 1:
                default:
                    $q .= '
                        AND
                        p.post_type = \'post\'';
                    break;
                
                case 2:
                    $q .= '
                        AND
                        p.post_type = \'page\'';
                    break;
            }
        }
        else
        {
            $q .= '
                AND
                p.post_type = \'post\'';
        }

        // post_category
        if( isset($args['post_category']) )
        {
            $temp_post_category = false;
        
            if( $args['post_category']>0 )
            {
                $temp_post_category = $args['post_category'];
            }
            elseif( $args['post_category']==-2 )
            {
                $temp_post_category = intval( get_query_var('cat') );
            }
            
            if( !empty($temp_post_category) )
            {
                $q .= '
                    AND
                    p.ID IN
                    (
                        SELECT
                            DISTINCT t_r.object_id
                        FROM
                            '.$wpdb->term_relationships.' t_r
                        WHERE
                            t_r.term_taxonomy_id = '.$temp_post_category.'
                    )';
            }
        }

        if( isset($args['post_category_exclude']) && $args['post_category_exclude']>0 )
        {
            $q .= '
                AND
                p.ID NOT IN
                (
                    SELECT
                        DISTINCT t_r.object_id
                    FROM
                        '.$wpdb->term_relationships.' t_r
                    WHERE
                        t_r.term_taxonomy_id = '.$args['post_category_exclude'].'
                )';
        }

        // ORDER, LIMIT
        $q .= '
            ORDER BY '.
                $sql_sorting_algorithm.
	            'p.post_date_gmt DESC
            LIMIT
                '.$args['count'];

	    return 
	        $wpdb->get_results($q);	
	        
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::getHTML()
	 * ( Returns HTML of Popular Posts )
	 *
	 * @param       array       $popular_posts
	 * @param       array       $args
	 * @return      string
	 */
	protected function getHTML( $popular_posts = array(), $args = array() )
	{	
		///////////////////////////////////////////////////////////////////////
	
	    if( empty($popular_posts) )
	    {
	        return false;
	    }
	    
		///////////////////////////////////////////////////////////////////////
	    
	    // fix bug in Wordpress :-)
        global $post;
        $tmp_post = $post;
	    
	    // args
	    $args = array_merge( $this->defaults, $args );
	    
	    $excerpt_length_isset = false;
	    
		///////////////////////////////////////////////////////////////////////
	    
	    $html = '<ul>';
	    $c = 1;
	    
	    foreach( $popular_posts as $post )
	    {
	        $post_author_obj = get_userdata( $post->post_author );
	        
	        $post_author_name = $post_author_obj->display_name;
	        $post_author_link = get_author_posts_url( $post_author_obj->ID, $post_author_obj->user_nicename );
	        
	        setup_postdata($post);

	        $temp_html = 
                str_ireplace(
                    array(
	                    '{post_id}',
	                    '{post_title}',
	                    '{post_author}',
	                    '{post_author_link}',
	                    '{permalink}',
	                    '{post_date}',	             
	                    '{post_hits}',       
	                    '{post_comments_count}',
	                    ),
                    array(
                        $post->ID,
                        //$post->post_title,
                        get_the_title(),
                        $post_author_name,
                        $post_author_link,
                        get_permalink($post->ID),
                        date( $args['post_date_format'], strtotime($post->post_date) ),
                        $post->post_hits,
                        $post->post_comments_count,
                        ),
                    $args['one_element_html']
                    );
                    
            if( preg_match_all( '#(\{thumbnail\-([^\}]+)\})#sim', $temp_html, $matches ) )
            {
                if( isset($matches['2']) && !empty($matches['2']) )
                {
                    foreach( $matches['2'] as $m )
                    {
                        $size = $m;
                    
                        if( preg_match( '#([0-9]+)x([0-9]+)#i', $m, $sizes ) )
                        {
                            if( isset($sizes['1']) && isset($sizes['2']) )
                            {
                                $size = array( $sizes['1'], $sizes['2'] );
                            }
                        }
                        
                        $temp_html = str_ireplace( '{thumbnail-'.$m.'}', get_the_post_thumbnail( $post->ID, $size ), $temp_html );
                    }
                }
            }
            
            if( stripos( $args['one_element_html'], '{post_categories}' )!==false )
            {
                $categories = get_the_category( $post->ID );
                
                if( !empty($categories) )
                {
                    $temp = array();
                
                    foreach( $categories as $category )
                    {
                        $temp[] = '<a href="'.get_category_link( $category->term_id ).'" title="'.esc_attr( $category->cat_name ).'">'.$category->cat_name.'</a>';
                    }
                    
	                $temp_html = str_ireplace( '{post_categories}', join( $args['post_categories_separator'], $temp ), $temp_html );
                }
            }
            
            if( preg_match( '#(\{post\_title\_([0-9]+)\})#sim', $temp_html, $matches ) )
            {
                if( isset($matches['2']) && !empty($matches['2']) )
                {
                    $temp_title_excerpt = get_the_title();
                    $temp_title_excerpt_length = intval($matches['2']);

                    if( $temp_title_excerpt_length > 0 )
                    {
                        $temp_title_excerpt_arr = explode( ' ', $temp_title_excerpt );
                        
                        $temp_title_excerpt = 
                            join( 
                                ' ', 
                                array_slice( 
                                    $temp_title_excerpt_arr, 
                                    0, 
                                    $temp_title_excerpt_length 
                                )
                            );
                        
                        if( count($temp_title_excerpt_arr) > $temp_title_excerpt_length )
                        {
                            $temp_title_excerpt .= '...';
                        }
                    }
                    
                    $temp_html = str_ireplace( $matches['1'], $temp_title_excerpt, $temp_html );
                }
            }

            if( preg_match( '#(\{post\_excerpt\_([0-9]+)\})#sim', $temp_html, $matches ) )
            {
                if( isset($matches['2']) && !empty($matches['2']) )
                {
                    /*
                    $excerpt_length = intval($matches['2']);

                    if( $excerpt_length > 0 )
                    {
                        if( $excerpt_length_isset===false )
                        {
                            add_filter( 'excerpt_length', create_function( '', 'return '.$excerpt_length.';' ), 1024 );
                            
                            $excerpt_length_isset = true;
                        }
                    }
                    
                    $temp_html = str_ireplace( $matches['1'], get_the_excerpt(), $temp_html );
                    */
                    
                    $temp_excerpt = get_the_content();
                    $temp_excerpt_length = intval($matches['2']);

                    if( $temp_excerpt_length > 0 )
                    {
                        $temp_excerpt_arr = explode( ' ', $temp_excerpt );
                        
                        $temp_excerpt = 
                            join( 
                                ' ', 
                                array_slice( 
                                    $temp_excerpt_arr, 
                                    0, 
                                    $temp_excerpt_length 
                                )
                            );
                        
                        if( count($temp_excerpt_arr) > $temp_excerpt_length )
                        {
                            $temp_excerpt .= '...';
                        }
                    }
                    
                    $temp_html = str_ireplace( $matches['1'], $temp_excerpt, $temp_html );
                }            
            }

	        $html .= '<li class="item-num-'.$c.' item-id-'.$post->ID.'">'.$temp_html.'</li>';
	        
	        $c++;
	    }

	    $html .= '</ul>';
	    
		///////////////////////////////////////////////////////////////////////
	    
	    // restore $post (Wordpress bug fixing)
	    wp_reset_postdata();
	    $post = $tmp_post;
	    
		///////////////////////////////////////////////////////////////////////
	    
	    return $html;
	    
		///////////////////////////////////////////////////////////////////////
	}
	
	///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::clearCache()
	 * ( Clear transient widget cache )
	 *
	 * @return      bool
	 */
	public function clearCache()
	{
		///////////////////////////////////////////////////////////////////////
	
	    global $wpdb;
	
	    $q = '
	        SELECT
		        option_name     as name
	        FROM
		        '.$wpdb->options.'
	        WHERE	
	            option_name LIKE \'_transient_ajax_hits_counter_%\'';

	    $transients = $wpdb->get_results($q);
	    
	    if( !empty($transients) )
	    {
	        foreach( $transients as $transient )
	        {
	            delete_transient( str_replace( '_transient_', '', $transient->name ) );
	        }
	    }
	    
	    return true;
	    
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::update()
	 * ( Sanitize widget form values as they are saved. )
	 *
	 * @see WP_Widget::update()
	 *
	 * @param       array       $new_instance       Values just sent to be saved.
	 * @param       array       $old_instance       Previously saved values from database.
	 * @return      array                           Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) 
	{
		///////////////////////////////////////////////////////////////////////
	
	    // drop cache
	    $this->clearCache();
	    
		///////////////////////////////////////////////////////////////////////

	    // return sanitized data
		return array(
		    'title'                         => trim( strip_tags( $new_instance['title'] ) ),
            'sorting_algorithm'             => intval( preg_replace( '#[^0-9]#', '', $new_instance['sorting_algorithm'] ) ),
            'sorting_coefficient_n'         => intval( preg_replace( '#[^0-9]#', '', $new_instance['sorting_coefficient_n'] ) ),
            'sorting_coefficient_k'         => intval( preg_replace( '#[^0-9]#', '', $new_instance['sorting_coefficient_k'] ) ),
		    'count'                         => intval( preg_replace( '#[^0-9]#', '', $new_instance['count'] ) ),
		    'cache_lifetime'                => intval( preg_replace( '#[^0-9]#', '', $new_instance['cache_lifetime'] ) ),
		    'date_range'                    => intval( preg_replace( '#[^1-9]#', '', $new_instance['date_range'] ) ),
		    'one_element_html'              => trim( $new_instance['one_element_html'] ),
		    'post_type'                     => intval( preg_replace( '#[^012]#', '', $new_instance['post_type'] ) ),
		    'post_category'                 => intval( preg_replace( '#[^\-0-9]#', '', $new_instance['post_category'] ) ),
            'post_category_exclude'         => intval( preg_replace( '#[^\-0-9]#', '', $new_instance['post_category_exclude'] ) ),
		    'post_categories_separator'     => $new_instance['post_categories_separator'],
		    'post_date_format'              => trim( strip_tags( $new_instance['post_date_format'] ) ),
            'custom_css'                    => trim(
                                                   strip_tags(
                                                       str_ireplace(
                                                           '#'.$this->id_base.'-__i__',
                                                           '#'.$this->id_base.'-'.$this->number,
                                                           $new_instance['custom_css']
                                                       )
                                                   )
                                               ),
		);
		
		///////////////////////////////////////////////////////////////////////
	}
	
    ///////////////////////////////////////////////////////////////////////////

	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::form()
	 * ( Back-end widget form. )
	 *
	 * @see WP_Widget::form()
	 *
	 * @param       array       $instance           Previously saved values from database.
     * @return      html
	 */
	public function form( $instance ) 
	{
		///////////////////////////////////////////////////////////////////////
	
	    // defaults
	    $title                      = __('Popular Posts');
	    $sorting_algorithm          = $this->defaults['sorting_algorithm'];
	    $sorting_coefficient_n      = $this->defaults['sorting_coefficient_n'];
	    $sorting_coefficient_k      = $this->defaults['sorting_coefficient_k'];
	    $count                      = $this->defaults['count'];
	    $cache_lifetime             = $this->defaults['cache_lifetime'];
	    $date_range                 = $this->defaults['date_range'];
	    $one_element_html           = $this->defaults['one_element_html'];
	    $post_type                  = $this->defaults['post_type'];
	    $post_category              = $this->defaults['post_category'];
        $post_category_exclude      = $this->defaults['post_category_exclude'];
	    $post_categories_separator  = $this->defaults['post_categories_separator'];
	    $post_date_format           = $this->defaults['post_date_format'];
        $custom_css                 = '';
	
		///////////////////////////////////////////////////////////////////////
	
		if( isset($instance['title']) && strlen($instance['title'])>1 ) 
		{
			$title = $instance[ 'title' ];
		}

		if( isset($instance['sorting_algorithm']) && intval($instance['sorting_algorithm'])>0 ) 
		{
			$sorting_algorithm = intval($instance['sorting_algorithm']);
		}
		
		if( isset($instance['sorting_coefficient_n']) && intval($instance['sorting_coefficient_n'])>0 ) 
		{
			$sorting_coefficient_n = intval($instance['sorting_coefficient_n']);
		}
		
		if( isset($instance['sorting_coefficient_k']) && intval($instance['sorting_coefficient_k'])>0 ) 
		{
			$sorting_coefficient_k = intval($instance['sorting_coefficient_k']);
		}
		
		if( isset($instance['count']) && intval($instance['count'])>0 ) 
		{
			$count = intval($instance['count']);
		}
		
		if( isset($instance['cache_lifetime']) && intval($instance['cache_lifetime'])>0 ) 
		{
			$cache_lifetime = intval($instance['cache_lifetime']);
		}
		
		if( isset($instance['date_range']) && intval($instance['date_range'])>0 ) 
		{
			$date_range = intval($instance['date_range']);
		}

		if( isset($instance['post_type']) ) 
		{
			$post_type = intval($instance['post_type']);
		}

		if( isset($instance['post_category']) ) 
		{
			$post_category = intval($instance['post_category']);
		}

        if( isset($instance['post_category_exclude']) )
        {
            $post_category_exclude = intval($instance['post_category_exclude']);
        }

		if( isset($instance['post_categories_separator']) && strlen($instance['post_categories_separator'])>0 ) 
		{
			$post_categories_separator = $instance['post_categories_separator'];
		}
		
		if( isset($instance['post_date_format']) && strlen($instance['post_date_format'])>0 ) 
		{
			$post_date_format = $instance['post_date_format'];
		}
		
		if( isset($instance['one_element_html']) && strlen($instance['one_element_html'])>1 ) 
		{
			$one_element_html = $instance['one_element_html'];
		}

        if( isset($instance['custom_css']) )
        {
            $custom_css = $instance['custom_css'];
        }
        else
        {
            $temp_widget_id = $this->id_base.'-'.$this->number;

            $custom_css =
                '#'.$temp_widget_id.' { /* block style */ }'."\n".
                '#'.$temp_widget_id.' .widget-title { /* widget title style */ }'."\n".
                '#'.$temp_widget_id.' ul li .entry-content { /* one item style */ }';
        }

		///////////////////////////////////////////////////////////////////////
				
		echo(
            '<style type="text/css">
            .'.$this->defaults['widget_id'].'_div {
                display:block;
                clear:both;
            }
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_left,
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right {
                    width:390px;
                    float:left;
                    margin:0 20px 0 0;
                    display: block;
                }
                    .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_left div.sorting_coefficient_div {
                        margin:0 0 0 5px;
                        padding:0;
                    }
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right {
                    margin:0;
                    zoom: 1;
                }
                .'.$this->defaults['widget_id'].'_div .'.$this->defaults['widget_id'].'_div_right:after {
                    content: ".";
                    display: block;
                    height: 0;
                    clear: both;
                    visibility: hidden;
                }
            </style>'.
		    '<div class="'.$this->defaults['widget_id'].'_div">'.
                '<div class="'.$this->defaults['widget_id'].'_div_left">'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('title').'">Widget title:</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('title').'" name="'.$this->get_field_name('title').'" type="text" value="'.esc_attr($title).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('sorting_algorithm').'">Sorting algorithm:</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('sorting_algorithm').'" name="'.$this->get_field_name('sorting_algorithm').'" onChange="return '.$this->defaults['widget_id'].'_sortingAlgorithmOnChange(this.value, \''.$this->get_field_id('sorting_coefficient_div').'\');">'.
                            '<option value="1"'.( $sorting_algorithm<2 || $sorting_algorithm>3 ? ' selected="selected"' : '' ).'>Hits count</option>'.
                            '<option value="2"'.( $sorting_algorithm==2 ? ' selected="selected"' : '' ).'>Comments count</option>'.
                            '<option value="3"'.( $sorting_algorithm==3 ? ' selected="selected"' : '' ).'>N * Hits count + K * Comments count</option>'.
                        '</select>'.
                        '<div '.( $sorting_algorithm==3 ? 'style="display:block;"' : 'style="display:none;"' ).' id="'.$this->get_field_id('sorting_coefficient_div').'" class="sorting_coefficient_div">'.
                            'N = <input id="'.$this->get_field_id('sorting_coefficient_n').'" name="'.$this->get_field_name('sorting_coefficient_n').'" type="text" value="'.esc_attr($sorting_coefficient_n).'" /><br />'.
                            'K = <input id="'.$this->get_field_id('sorting_coefficient_k').'" name="'.$this->get_field_name('sorting_coefficient_k').'" type="text" value="'.esc_attr($sorting_coefficient_k).'" />'.
                        '</div>'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('count').'">Display count:</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('count').'" name="'.$this->get_field_name('count').'" type="text" value="'.esc_attr($count).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('cache_lifetime').'">Cache lifetime (seconds):</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('cache_lifetime').'" name="'.$this->get_field_name('cache_lifetime').'" type="text" value="'.esc_attr($cache_lifetime).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_type').'">Posts types:</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('post_type').'" name="'.$this->get_field_name('post_type').'">'.
                            '<option value="0"'.( $post_type==0 ? ' selected="selected"' : '' ).'>Posts & Pages</option>'.
                            '<option value="1"'.( $post_type==1 ? ' selected="selected"' : '' ).'>Posts only</option>'.
                            '<option value="2"'.( $post_type==2 ? ' selected="selected"' : '' ).'>Pages only</option>'.
                            //  TODO: add custom types
                        '</select>'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('date_range').'">Posts date range:</label>'.
                        '<select class="widefat" id="'.$this->get_field_id('date_range').'" name="'.$this->get_field_name('date_range').'">'.
                            '<option value="1"'.( $date_range<=1 ? ' selected="selected"' : '' ).'>Day</option>'.
                            '<option value="2"'.( $date_range==2 ? ' selected="selected"' : '' ).'>Week</option>'.
                            '<option value="3"'.( $date_range==3 ? ' selected="selected"' : '' ).'>Month</option>'.
                            '<option value="4"'.( $date_range==4 ? ' selected="selected"' : '' ).'>3 Months</option>'.
                            '<option value="5"'.( $date_range==5 ? ' selected="selected"' : '' ).'>6 Months</option>'.
                            '<option value="6"'.( $date_range==6 ? ' selected="selected"' : '' ).'>Year</option>'.
                            '<option value="7"'.( $date_range>=7 ? ' selected="selected"' : '' ).'>All time</option>'.
                        '</select>'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_category').'">Include category (only for "posts" type):</label>'.
                        $this->_dropdownCategories(
                            array(
                                'id'                => $this->get_field_id('post_category'),
                                'name'              => $this->get_field_name('post_category'),
                                'selected'          => $post_category,
                                )
                            ).
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_category_exclude').'">Exclude posts from this category (only for "posts" type):</label>'.
                        $this->_dropdownCategories(
                            array(
                                'id'                => $this->get_field_id('post_category_exclude'),
                                'name'              => $this->get_field_name('post_category_exclude'),
                                'selected'          => $post_category_exclude,
                                'display_any'       => false,
                                'display_current'   => false,
                                'display_none'      => true,
                            )
                        ).
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_categories_separator').'">Categories separator (if more than one):</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('post_categories_separator').'" name="'.$this->get_field_name('post_categories_separator').'" type="text" value="'.esc_attr($post_categories_separator).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('post_date_format').'">Date format (for more info see <a href="http://php.net/manual/en/function.date.php" target="_BLANK">date() manual</a>):</label>'.
                        '<input class="widefat" id="'.$this->get_field_id('post_date_format').'" name="'.$this->get_field_name('post_date_format').'" type="text" value="'.esc_attr($post_date_format).'" />'.
                    '</p>'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('custom_css').'">Custom CSS (remove if unneeded):</label>'.
                        '<textarea class="widefat" cols="20" rows="5" id="'.$this->get_field_id('custom_css').'" name="'.$this->get_field_name('custom_css').'">'.$custom_css.'</textarea>'.
                    '</p>'.
                '</div>'.
                '<div class="'.$this->defaults['widget_id'].'_div_right">'.
                    '<p>'.
                        '<label for="'.$this->get_field_id('one_element_html').'">HTML of one element/item (inside &lt;LI&gt;):</label>'.
                        '<textarea class="widefat" cols="20" rows="8" id="'.$this->get_field_id('one_element_html').'" name="'.$this->get_field_name('one_element_html').'">'.$one_element_html.'</textarea>'.
                        'You can use this placeholders:'.
                        '<ul>'.
                            '<li><code>{post_id}</code> - Post ID</li>'.
                            '<li><code>{post_title}</code> - Post title</li>'.
                            '<li><code>{post_title_N}</code> - Post title, where <code>N</code> - is words count<br />&nbsp;&nbsp;For example: <code>{post_title_16}</code></li>'.
                            '<li><code>{post_excerpt_N}</code> - Post excerpt, where <code>N</code> - is words count<br />&nbsp;&nbsp;For example: <code>{post_excerpt_10}</code> or <code>{post_excerpt_255}</code></li>'.
                            '<li><code>{post_author}</code> - Post author name</li>'.
                            '<li><code>{post_author_link}</code> - Post author link</li>'.
                            '<li><code>{permalink}</code> - Post link</li>'.
                            '<li><code>{post_date}</code> - Post date</li>'.
                            '<li><code>{thumbnail-[medium|...|64x64]}</code> - Post thumbnail with size<br />&nbsp;&nbsp;For example: <code>{thumbnail-large}</code> or <code>{thumbnail-320x240}</code>'.
                            '<li><code>{post_categories}</code> - Links to post categories with <code>'.$post_categories_separator.'</code> as separator</li>'.
                            '<li><code>{post_hits}</code> - Post hits, counted by this plugin</li>'.
                            '<li><code>{post_comments_count}</code> - Post comments count</li>'.
                        '</ul>'.
                    '</p>'.
                '</div>'.
		    '</div>'.
            '<script>                       
            function '.$this->defaults['widget_id'].'_sortingAlgorithmOnChange(val, div_id)
            {
                if( val==3 )
                {
                    document.getElementById(div_id).style.display = "block";
                }
                else
                {
                    document.getElementById(div_id).style.display = "none";
                }
                
                return true;
            }                   
            </script>'
		    );
		    
		///////////////////////////////////////////////////////////////////////
	}

    ///////////////////////////////////////////////////////////////////////////
	
	/**
	 * AJAX_Hits_Counter_Popular_Posts_Widget::_dropdownCategories()
	 * ( Dropdown categories )
     *
	 * @param       array       $args
	 * @return      string      $html
	 */
	private function _dropdownCategories( $args = array() )
	{
        $args = array_merge(
            array(
                'id'                => 'categories_'.uniqid(),
                'name'              => 'categories_'.uniqid(),
                'selected'          => false,
                'class'             => 'widefat',
                'display_any'       => true,
                'display_current'   => true,
                'display_none'      => false,
                ),
            $args
            );
            
		///////////////////////////////////////////////////////////////////////

        $html = 
            '<select id="'.$args['id'].'" name="'.$args['name'].'" class="'.$args['class'].'">'.
                ( $args['display_any']          ? '<option value="-1"'.( $args['selected']==-1 ? ' selected="selected"' : '' ).'>Any</option>'                          : '' ).
                ( $args['display_current']      ? '<option value="-2"'.( $args['selected']==-2 ? ' selected="selected"' : '' ).'>Current Category / Any</option>'       : '' ).
                ( $args['display_none']         ? '<option value="-3"'.( $args['selected']==-3 ? ' selected="selected"' : '' ).'>None</option>'                         : '' );
                
		///////////////////////////////////////////////////////////////////////

        $categories_levels = array();

        $categories_sortbyid = get_categories(
            array(  
                'type'                     => 'post',
	            'orderby'                  => 'id',
	            'order'                    => 'ASC',
	            'hide_empty'               => 0,
	            'hierarchical'             => 1,
            )
        );

        foreach( $categories_sortbyid as $c )
        {
            $categories_levels[ $c->cat_ID ] = ( isset($categories_levels[ $c->category_parent ]) ? ( $categories_levels[ $c->category_parent ] + 1 ) : 1 );
        }
        
        unset( $categories_sortbyid );

		///////////////////////////////////////////////////////////////////////

        $categories = get_categories(
            array(  
                'type'                     => 'post',
	            'orderby'                  => 'name',
	            'order'                    => 'ASC',
	            'hide_empty'               => 0,
	            'hierarchical'             => 1,
            )
        );

		///////////////////////////////////////////////////////////////////////
            
        foreach( $categories as $c )
        {
            $html .= 
                '<option value="'.$c->cat_ID.'"'.( $args['selected']==$c->cat_ID ? ' selected="selected"' : '' ).'>'.
                    ( $categories_levels[$c->cat_ID]>1 ? str_repeat( '-', $categories_levels[$c->cat_ID] ).' ' : '' ).$c->cat_name.
                '</option>';
        }
        
        $html .=
            '</select>';
                
        return $html;
	}

    ///////////////////////////////////////////////////////////////////////////
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

// init Ajax Hits Counter
$ahc = new AJAX_Hits_Counter();
$ahc->init();

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////

function ajax_hits_counter_get_hits( $post_id ) 
{
    $ahc = new AJAX_Hits_Counter();
    
    return 
        $ahc->getHits( $post_id );
}

//////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
