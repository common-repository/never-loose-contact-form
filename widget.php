<?php

function never_loose_contact_form_register_widget() {

 register_widget( 'never_loose_contact_form_Widget' );
  
}
add_action( 'widgets_init', 'never_loose_contact_form_register_widget' );

class never_loose_contact_form_Widget extends WP_Widget {
    function __construct() {

     $widget_options = array (
      'classname' => 'never_loose_contact_form_widget',
      'description' => 'Never Loose Contact Form'
     );

     parent::__construct( 'never_loose_contact_form_widget', 'Never Loose Contact Form', $widget_options );

    }
    function form( $instance ) 
    {
        
                echo '<p> <label for="'.$this->get_field_id( 'title' ).'">Title</label><input class="widefat" type="text" id="'.$this->get_field_id('title' ).'" name="'.$this->get_field_name( 'title').'"/></p>';
    }
    
    //function to define the data saved by the widget

    function update( $new_instance, $old_instance ) 
    {
        $instance = $old_instance;
        
        $instance['title']= stripslashes($new_instance[ 'title']) ;
     
        return $instance;          

    }
    //function to display the widget in the site

    function widget( $args, $instance ) {
         //define variables
        if(empty($instance['title']))$instance['title']=__('Contact Us','church-admin');
         $title = apply_filters( 'widget_title', $instance['title'] );
            
        echo $args['before_widget'];
        echo'<h2>'.$title.'</h2>';
         echo  contact_form(TRUE,false);
         echo $args['after_widget'];

    }
}



