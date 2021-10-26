<?php
/**
 * The template for displaying restricted messages 
 */
get_header();

/* Start the Loop */
while (have_posts()) :
    the_post();
    ?>
    <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <?php if (!is_front_page()) : ?>
            <header class="entry-header alignwide">
                <?php get_template_part('template-parts/header/entry-header'); ?>
                <?php twenty_twenty_one_post_thumbnail(); ?>
            </header>
        <?php elseif (has_post_thumbnail()) : ?>
            <header class="entry-header alignwide">
                <?php twenty_twenty_one_post_thumbnail(); ?>
            </header>
        <?php endif; ?>


        <div class="entry-content alignwide">
            <span class="page-description">
                <?php
                echo get_option('restricted_descriptions');
                ?>
            </span>
        </div>


        <?php if (get_edit_post_link()) : ?>
            <footer class="entry-footer default-max-width">
                <?php
                edit_post_link(
                        sprintf(
                                /* translators: %s: Name of current post. Only visible to screen readers. */
                                esc_html__('Edit %s', 'twentytwentyone'), '<span class="screen-reader-text">' . get_the_title() . '</span>'
                        ), '<span class="edit-link">', '</span>'
                );
                ?>
            </footer><!-- .entry-footer -->
            <?php endif; ?>
    </article>
    <style>
        .page-description{
            margin: 0 auto;
            width: 100%;
            padding: 0 20px;
        }
    </style>
    <?php
endwhile;

get_footer();
