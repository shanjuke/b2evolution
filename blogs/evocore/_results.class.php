<?php
/**
 * This file implements the Results class.
 *
 * This file is part of the b2evolution/evocms project - {@link http://b2evolution.net/}.
 * See also {@link http://sourceforge.net/projects/evocms/}.
 *
 * @copyright (c)2003-2005 by Francois PLANQUE - {@link http://fplanque.net/}.
 * Parts of this file are copyright (c)2004 by PROGIDISTRI - {@link http://progidistri.com/}.
 *
 * @license http://b2evolution.net/about/license.html GNU General Public License (GPL)
 * {@internal
 * b2evolution is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * b2evolution is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with b2evolution; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 * }}
 *
 * {@internal
 * PROGIDISTRI grants Francois PLANQUE the right to license
 * PROGIDISTRI's contributions to this file and the b2evolution project
 * under any OSI approved OSS license (http://www.opensource.org/licenses/).
 * }}
 *
 * @package evocore
 *
 * {@internal Below is a list of authors who have contributed to design/coding of this file: }}
 * @author fplanque: Francois PLANQUE
 * @author fsaya: Fabrice SAYA-GASNIER / PROGIDISTRI
 *
 * @version $Id$
 */
if( !defined('EVO_MAIN_INIT') ) die( 'Please, do not access this page directly.' );

/**
 * Includes:
 */
require_once dirname(__FILE__).'/_widget.class.php';


/**
 * Results class
 */
class Results extends Widget
{
	var $DB;

	/**
	 * SQL query
	 */
	var $sql;

	/**
	 * Total number of rows (if > $limit, it will result in multiple pages)
	 */
	var $total_rows;

	/**
	 * Number of lines per page
	 */
	var $limit;

	/**
	 * Number of rows in result set for current page.
	 */
	var $result_num_rows;

	/**
	 * Total number of pages
	 */
	var $total_pages;

	/**
	 * Current page
	 */
	var $page;

	/**
	 * Array of DB rows for current page.
	 */
	var $rows;

	/**
	 * List of IDs for current page.
	 * @uses Results::ID_col
	 */
	var $page_ID_list;

	/**
	 * Array of IDs for current page.
	 * @uses Results::ID_col
	 */
	var $page_ID_array;

 	/**
	 * Current object idx in $rows array:
	 */
	var $current_idx = 0;

	/**
	 * Cache to use to instantiate an object and cache it for each line of results.
	 *
	 * For this to work, all columns of the related table must be selected in the query
	 */
	var $Cache;

	/**
	 * This will hold the object instantiated by the Cache for the current line.
	 */
	var $current_Obj;


	/**
	 * Definitions for each column:
	 * -th
	 * -td
	 * -order
	 * -td_start. A column with no def will de displayed using
	 * the default defs from Results::params, that is to say, one of these:
	 *   - $this->params['col_start_first'];
	 *   - $this->params['col_start_last'];
	 *   - $this->params['col_start'];
	 */
	var $cols;

	/**
	 * Do we want to display column headers?
	 * @var boolean
	 */
	var $col_headers = true;


	/**
	 * Display parameters
	 */
	var $params = NULL;


	/**
	 * Fieldname to group on.
	 *
	 * Leave empty if you don't want to group.
	 *
	 * @var string
	 */
	var $group_by = '';

 	/**
	 * Current group identifier:
	 * @var string
	 */
	var $current_group_ID = 0;

	/**
	 * Definitions for each GROUP column:
	 * -td
	 * -td_start. A column with no def will de displayed using
	 * the default defs from Results::params, that is to say, one of these:
	 *   - $this->params['grp_col_start_first'];
	 *   - $this->params['grp_col_start_last'];
	 *   - $this->params['grp_col_start'];
	 */
	var $grp_cols = NULL;

	/**
	 * Fieldname to detect empty data rows.
	 *
	 * Empty data rows can happen when left joining on groups.
	 * Leave empty if you don't want to detect empty datarows.
	 *
	 * @var string
	 */
	var $ID_col = '';


	/**
	 * URL param names
	 */
	var $param_prefix;
	var $page_param;
	var $order_param;


	/**
	 * Constructor
	 *
	 *
	 * @todo we might not want to count total rows when not needed...
	 * @todo fplanque: I am seriously considering putting $count_sqlinto 2nd or 3rd position. Any prefs?
	 *
	 * @param string SQL query
	 * @param string prefix to differentiate page/order params when multiple Results appear one same page
	 * @param string default ordering of columns (special syntax) if not URL specified
	 * @param integer number of lines displayed on one page
	 * @param NULL|string SQL query used to count the total # of rows (if NULL, we'll try to COUNT(*) by ourselves)
	 */
	function Results( $sql, $param_prefix = '', $default_order = '', $limit = 20, $count_sql = NULL,
									$init_page = true )
	{
		global $DB;
		$this->DB = & $DB;
		$this->sql = $sql;
		$this->limit = $limit;
		$this->param_prefix = $param_prefix;

		// Count total rows:
		$this->count_total_rows( $count_sql );

		if( $init_page )
		{	//attribution of a page number
			$this->page_param = 'results_'.$param_prefix.'page';
			$page = param( $this->page_param, 'integer', 1, true );
			$this->page = min( $page, $this->total_pages );
		}

		//attribution of an order type
		$this->order_param = 'results_'.$param_prefix.'order';
 		$this->order = param( $this->order_param, 'string', $default_order, true );
	}


	/**
	 * Rewind resultset
	 *
	 * {@internal DataObjectList::restart(-) }}
	 */
	function restart()
	{
		// Make sure query has exexuted:
		$this->query( $this->sql );

		$this->current_idx = 0;

		$this->current_group_ID = 0;
	}


	/**
	 * Run the query now!
	 *
	 * Will only run if it has not executed before.
	 *
	 * @todo do we need that $sql param ???
	 */
	function query( $sql, $create_default_cols_if_needed = true, $append_limit = true )
	{
		if( !is_null( $this->rows ) )
		{ // Query has already executed:
			return;
		}

		// Make sure we have colum definitions:
		if( is_null( $this->cols ) && $create_default_cols_if_needed )
		{ // Let's create default column definitions:
			$this->cols = array();

			if( !preg_match( '#SELECT \s+ (.+?) \s+ FROM#six', $this->sql, $matches ) )
			{
				die( 'Results->query() : No SELECT clause!' );
			}

			// Split requested columns by commata
			foreach( preg_split( '#\s*,\s*#', $matches[1] ) as $l_select )
			{
				if( is_numeric( $l_select ) )
				{ // just a single value (would produce parse error as '$x$')
					$this->cols[] = array( 'td' => $l_select );
				}
				elseif( preg_match( '#^(\w+)$#i', $l_select, $match ) )
				{ // regular column
					$this->cols[] = array( 'td' => '$'.$match[1].'$' );
				}
				elseif( preg_match( '#^(.*?) AS (\w+)#i', $l_select, $match ) )
				{ // aliased column
					$this->cols[] = array( 'td' => '$'.$match[2].'$' );
				}
			}

			if( !isset($this->cols[0]) )
			{
				die( 'No columns selected!' );
			}
		}



		// Append ORDER clause if necessary:
		if( $orders = $this->get_order_field_list() )
		{	// We have orders to append

			if( strpos( $this->sql, 'ORDER BY') === false )
			{ // there is no ORDER BY clause in the original SQL query
				$this->sql .= ' ORDER BY '.$orders.' ';
			}
			else
			{ // the chosen order must be appended to an existing ORDER BY clause
				$this->sql .= ', '.$orders.' ';
			}
		}


		if( $append_limit && !empty($this->limit) )
		{	// Limit lien range to requested page
			$sql = $this->sql.' LIMIT '.max(0, ($this->page-1)*$this->limit).', '.$this->limit;
		}

		// Execute query and store results
		$this->rows = $this->DB->get_results( $sql );

		// Store row count
		$this->result_num_rows = $this->DB->num_rows;

		// echo '<br />rows on page='.$this->result_num_rows;
	}


	/**
	 * Get a list of IDs for current page
	 *
 	 * @uses Results::ID_col
	 */
	function get_page_ID_list()
	{
		if( is_null( $this->page_ID_list ) )
		{
			$this->page_ID_list = implode( ',', $this->get_page_ID_array() );
			//echo '<br />'.$this->page_ID_list;
		}

		return $this->page_ID_list;
	}


	/**
	 * Get an array of IDs for current page
	 *
 	 * @uses Results::ID_col
	 */
	function get_page_ID_array()
	{
		if( is_null( $this->page_ID_array ) )
		{
			$this->page_ID_array = array();

			foreach( $this->rows as $row )
			{ // For each row/line:
				$this->page_ID_array[] = $row->{$this->ID_col};
			}
		}

		return $this->page_ID_array;
	}


	/**
	 * Count the total number of rows of the SQL result (all pages)
	 *
	 * This is done by dynamically modifying the SQL query and forging a COUNT() into it.
	 *
	 * @todo allow overriding?
	 * @todo handle problem of empty groups!
	 */
	function count_total_rows( $sql_count = NULL )
	{
		if( empty( $sql_count ) )
		{
			if( is_null($this->sql) )
			{ // We may want to remove this later...
				$this->total_rows = 0;
				$this->total_pages = 0;
				return;
			}

 			$sql_count = $this->sql;
			// echo $sql_count;

			/*
			 *
			 * On a un probl�me avec la recherche sur les soci�t�s
			 * si on fait un select count(*), �a sort un nombre de r�ponses �norme
			 * mais on ne sait pas pourquoi... la solution est de lister des champs dans le COUNT()
			 * MAIS malheureusement �a ne fonctionne pas pour d'autres requ�tes.
			 * L'id�al serait de r�ussir � isoler qu'est-ce qui, dans la requ�te SQL, provoque le comportement
			 * bizarre....
			 */
			// Tentative 1:
			// if( !preg_match( '#FROM(.*?)((WHERE|ORDER BY|GROUP BY) .*)?$#si', $sql_count, $matches ) )
			//  die( "Can't understand query..." );
			// if( preg_match( '#(,|JOIN)#si', $matches[1] ) )
			// { // there was a coma or a JOIN clause in the FROM clause of the original query,
			// Tentative 2:
			// fplanque: je pense que la diff�rence est sur la pr�sence de DISTINCT ou non.
			// if( preg_match( '#\s DISTINCT \s#six', $sql_count, $matches ) )
			if( preg_match( '#\s DISTINCT \s+ ([A-Za-z_]+)#six', $sql_count, $matches ) )
			{ //
				// Get rid of any Aliases in colmun names:
				// $sql_count = preg_replace( '#\s AS \s+ ([A-Za-z_]+) #six', ' ', $sql_count );
				// ** We must use field names in the COUNT **
				//$sql_count = preg_replace( '#SELECT \s+ (.+?) \s+ FROM#six', 'SELECT COUNT( $1 ) FROM', $sql_count );

				//Tentative 3: we do a distinct on the first field only when counting:
				$sql_count = preg_replace( '#SELECT \s+ (.+?) \s+ FROM#six', 'SELECT COUNT( DISTINCT '.$matches[1].' ) FROM', $sql_count );
			}
			else
			{ // Single table request: we must NOT use field names in the count.
				$sql_count = preg_replace( '#SELECT \s+ (.+?) \s+ FROM#six', 'SELECT COUNT( * ) FROM', $sql_count );
			}

			// echo $sql_count;
		}

		$this->total_rows = $this->DB->get_var( $sql_count ); //count total rows

		$this->total_pages = empty($this->limit) ? 1 : ceil($this->total_rows / $this->limit);
	}


	/**
	 * Note: this function might actually not be very useful.
	 * If you define ->Cache before display, all rows will be instantiated on the fly.
	 * No need to restart et go through the rows a second time here.
	 *
	 * @params DataObjectCache
	 */
	function instantiate_page_to_Cache( & $Cache )
	{
		$this->Cache = & $Cache;

		// Make sure query has executed and we're at the top of the resultset:
		$this->restart();

		foreach( $this->rows as $row )
		{ // For each row/line:

			// Instantiate an object for the row and cache it:
			$this->Cache->instantiate( $row );
		}

	}


	/**
	 * Display paged list/table based on object parameters
	 *
	 * This is the meat of this class!
	 *
	 * @return int # of rows displayed
	 */
	function display( $display_params = NULL )
	{
		// Make sure we have display parameters:
		if( !is_null($display_params) )
		{ // Use passed params:
			$this->params = & $display_params;
		}
		elseif( empty( $this->params ) )
		{ // Use default params from Admin Skin:
			global $AdminUI;
			$this->params = $AdminUI->get_menu_template( 'Results' );
		}


		// Make sure query has executed and we're at the top of the resultset:
		$this->restart();


		// -------------------------
		// Proceed with display:
		// -------------------------
		echo $this->params['before'];

			if( $this->total_pages == 0 )
			{ // There are no results! Nothing to display!
				echo $this->replace_vars( $this->params['no_results'] );
			}
			else
			{	// We have rows to display:

				// GLOBAL (NAV) HEADER:
				$this->display_nav( 'header' );

				$this->display_top_callback();

				// START OF LIST/TABLE:
				$this->display_list_start();

					// COLUMN HEADERS:
					$this->display_head();

					// GROUP & DATA ROWS:
					$this->display_body();

				// END OF LIST/TABLE:
				$this->display_list_end();

				// GLOBAL (NAV) FOOTER:
				$this->display_nav( 'footer' );
			}

		echo $this->params['after'];

		// Return number of rows diplayed:
		return $this->current_idx;
	}


	// EXPERIMENTAL:
	function display_top_callback()
	{
		if( !empty($this->top_callback) )
		{
			$this->Form = new Form( regenerate_url(), $this->param_prefix.'form_search', 'post', 'none' ); // COPY!!

			$this->Form->begin_form( '' );

			$func = $this->top_callback;
			$func( $this->Form );
			$this->Form->submit( array( 'submit', T_('Filter list'), 'search' ) );

			$this->Form->end_form( '' );
		}
	}


	/**
	 * Display list/table start.
	 *
	 * Typically outputs <ul> or <table>
	 *
	 * @access protected
	 */
	function display_list_start()
	{
		echo $this->params['list_start'];
	}


	/**
	 * Display list/table end.
	 *
	 * Typically outputs </ul> or </table>
	 *
	 * @access protected
	 */
	function display_list_end()
	{
		echo $this->params['list_end'];
	}


	/**
	 * Display list/table head.
	 *
	 * This includes list head/title and column headers.
	 * This is optional and will only produce output if column headers are defined.
	 * EXPERIMENTAL: also dispays <tfoot>
	 *
	 * @access protected
	 */
	function display_head()
	{
		if( ! $this->col_headers )
		{ // We do not want to display headers:
			return false;
		}

		echo $this->params['head_start'];

		if( isset($this->title) )
		{ // A title has been defined for this result set:
			echo $this->replace_vars( $this->params['head_title'] );
		}

		$col_count = 0;
		$col_names = array();
		foreach( $this->cols as $col )
		{ // For each column:

			if( isset( $col['th_start'] ) )
			{ // We have a customized column start for this one:
				echo $col['th_start'];
			}
			elseif( ($col_count==0) && isset($this->params['colhead_start_first']) )
			{ // First column can get special formatting:
				echo $this->params['colhead_start_first'];
			}
			elseif( ($col_count==count($this->cols)-1) && isset($this->params['colhead_start_last']) )
			{ // Last column can get special formatting:
				echo $this->params['colhead_start_last'];
			}
			else
			{ // Regular columns:
				echo $this->params['colhead_start'];
			}

			if( isset( $col['order'] ) )
			{ //the column can be ordered

				$order_asc = '';
				$order_desc = '';
				$color_asc = '';
				$color_desc = '';

				for( $i = 0, $icount = count($this->cols); $i < $icount; $i++)
				{ // construction of the values which can be taken by $order
					if( !empty( $this->default_col ) && !strcasecmp( $col['order'], $this->default_col ) )
					{ // there is a default order
						$order_asc.='A';
						$order_desc.='D';
					}
					elseif(	$i == $col_count )
					{ //link ordering the current column
						$order_asc.='A';
						$order_desc.='D';
					}
					else
					{
						$order_asc.='-';
						$order_desc.='-';
					}
				}

				$style = $this->params['sort_type'];

				$asc_status = ( strstr( $this->order, 'A' ) && $col_count == strpos( $this->order, 'A') ) ? 'on' : 'off' ;
				$desc_status = ( strstr( $this->order, 'D' ) && $col_count == strpos( $this->order, 'D') ) ? 'on' : 'off' ;
				$sort_type = ( strstr( $this->order, 'A' ) && $col_count == strpos( $this->order, 'A') ) ? $order_desc : $order_asc;
				$title = strstr( $sort_type, 'A' ) ? T_('Ascending order') : T_('Descending order');
				$title = ' title="'.$title.'" ';

				$pos =  strpos( $this->order, 'D');

				if( strstr( $this->order, 'A' ) )
				{
					$pos = strpos( $this->order, 'A' );
				}

				if( $col_count == $pos )
				{ //the column header must be displayed in bold
					$class = ' class="'.$style.'_current" ';
				}
				else
				{
					$class = ' class="'.$style.'_sort_link" ';
				}

				if( $this->params['sort_type'] == 'single' )
				{ // single sort mode:

					echo '<a href="'.regenerate_url( $this->order_param, $this->order_param.'='.$sort_type )
								.'" '.$title.$class.' >'
								.$col['th'].'</a>'
								.'<a href="'.regenerate_url( $this->order_param, $this->order_param.'='.$order_asc )
								.'" title="'.T_('Ascending order')
								.'" '.$class.' >'.$this->params['sort_asc_'.$asc_status].'</a>'
								.'<a href="'.regenerate_url( $this->order_param, $this->order_param.'='.$order_desc )
								.'" title="'.T_('Descending order')
								.'" '.$class.' >'.$this->params['sort_desc_'.$desc_status].'</a> ';
				}
				elseif( $this->params['sort_type'] == 'basic' )
				{ // basic sort mode:
					if( $asc_status == 'off' && $desc_status == 'off' )
					{ // the sorting is not made on the current column
						$sort_item = $this->params['basic_sort_off'];
					}
					elseif( $asc_status == 'on' )
					{ // the sorting is ascending and made on the current column
						$sort_item = $this->params['basic_sort_asc'];
					}
					elseif( $desc_status == 'on' )
					{ // the sorting is descending and made on the current column
						$sort_item = $this->params['basic_sort_desc'];
					}

					echo '<a href="'.regenerate_url( $this->order_param, $this->order_param.'='.$sort_type ).'" title="'.T_('Change Order')
								.'" '.$class.' >'.$sort_item.' '.$col['th'].'</a>';
				}
			}
			elseif( isset($col['th']) )
			{ // the column can't be ordered, but we still have a header defined:
				echo $col['th'];
			}
			$col_count++;

			echo $this->params['colhead_end'];

		}

		echo $this->params['head_end'];


		// experimental:
		echo $this->params['tfoot_start'];
		echo $this->params['tfoot_end'];
	}


	/**
	 * Display list/table body.
	 *
	 * This includes groups and data rows.
	 *
	 * @access protected
	 */
	function display_body()
	{
		echo $this->params['body_start'];

		$line_count = 0;
		foreach( $this->rows as $row )
		{ // For each row/line:

			/*
			 * Group row stuff:
			 */
			if( !empty($this->group_by) )
			{	// We are grouping...
				if( $row->{$this->group_by} != $this->current_group_ID )
				{	// We have just entered a new group!
					// memorize new group identifier:
					$this->current_group_ID = $row->{$this->group_by};

					echo '<tr class="group">';

					$col_count = 0;
					foreach( $this->grp_cols as $grp_col )
					{ // For each column:
						if( isset( $grp_col['td_start'] ) )
						{ // We have a customized column start for this one:
							$output = $grp_col['td_start'];
						}
						elseif( ($col_count==0) && isset($this->params['grp_col_start_first']) )
						{ // Display first column column start:
							$output = $this->params['col_start_first'];
						}
						elseif( ($col_count==count($this->cols)-1) && isset($this->params['grp_col_start_last']) )
						{ // Last column can get special formatting:
							$output = $this->params['grp_col_start_last'];
						}
						else
						{ // Display regular colmun start:
							$output = $this->params['grp_col_start'];
						}

						// Contents to output:
						$output .= $this->parse_col_content( $grp_col['td'] );
						//echo $output;
						eval( "echo '$output';" );

						echo '</td>';
						$col_count++;
					}

					echo '</tr>';

				}
			}


			/*
			 * Data row stuff:
			 */
			if( !empty($this->ID_col) && empty($row->{$this->ID_col}) )
			{	// We have detected an empty data row which we want to ignore... (happens with empty groups)
				continue;
			}


			if( ! is_null( $this->Cache ) )
			{ // We want to instantiate an object for the row and cache it:
				// We also keep a local ref in case we want to use it for display:
				$this->current_Obj = & $this->Cache->instantiate( $row );
			}


			if( $this->current_idx % 2 )
			{ // Odd line:
				if( $this->current_idx == count($this->rows)-1 )
					echo $this->params['line_start_odd_last'];
				else
					echo $this->params['line_start_odd'];
			}
			else
			{ // Even line:
				if( $this->current_idx == count($this->rows)-1 )
					echo $this->params['line_start_last'];
				else
					echo $this->params['line_start'];
			}

			$col_count = 0;
			foreach( $this->cols as $col )
			{ // For each column:

				if( isset( $col['td_start'] ) )
				{ // We have a customized column start for this one:
					$output = $col['td_start'];
				}
				elseif( ($col_count==0) && isset($this->params['col_start_first']) )
				{ // Display first column column start:
					$output = $this->params['col_start_first'];
				}
				elseif( ($col_count==count($this->cols)-1) && isset($this->params['col_start_last']) )
				{ // Last column can get special formatting:
					$output = $this->params['col_start_last'];
				}
				else
				{ // Display regular colmun start:
					$output = $this->params['col_start'];
				}

				// Contents to output:
				$output .= $col['td'];

				$output .= $this->params['col_end'];

				$output = $this->parse_col_content($output);
				// echo '{'.$output.'}';
				eval( "echo '$output';" );

				$col_count++;
			}
			echo $this->params['line_end'];
			$this->current_idx++;
		}

		echo $this->params['body_end'];
	}


	/**
	 * Display navigation text, based on template.
	 *
	 * @param string template: 'header' or 'footer'
	 *
	 * @access protected
	 */
	function display_nav( $template )
	{
		echo $this->params[$template.'_start'];

		if( ( $this->total_pages <= 1 ) )
		{
			echo $this->params[$template.'_text_single'];
		}
		else
		{
			echo $this->replace_vars( $this->params[$template.'_text'] );
		}

		echo $this->params[$template.'_end'];
	}


	/**
	 * Returns order field list add to SQL query:
	 */
	function get_order_field_list()
	{
		if( empty( $this->order ) )
		{ // We have no user provided order:
			if( empty( $this->cols ) )
			{	// We have no columns to pick an automatic order from:
				// echo 'Can\'t determine automatic order';
				return '';
			}

			foreach( $this->cols as $col )
			{
				if( isset( $col['order'] ) )
				{ // We have found the first orderable column:
					$this->order .= 'A';
					break;
				}
				else
				{
					$this->order .= '-';
				}
			}

			if( empty( $this->cols ) )
			{	// We did not find any column to order on...
				return '';
			}
		}

		// echo ' order='.$this->order.' ';

		$orders = array();

    for( $i = 0; $i <= strlen( $this->order ); $i++ )
    {	// For each position in order string:
			if( isset( $this->cols[$i]['order'] ) )
			{	// if column is sortable:
				switch( substr( $this->order, $i, 1 ) )
				{
					case 'A':
						$orders[] = str_replace( ',', ' ASC,', $this->cols[$i]['order']).' ASC';
						break;

					case 'D':
						$orders[] = str_replace( ',', ' DESC,', $this->cols[$i]['order']).' DESC';
						break;
				}
			}
		}

		return implode(',',$orders);	// May be empty
	}


	/**
	 * Handle variable subtitutions for column contents.
	 *
	 * This is one of the key functions to look at when you want to use the Results class.
	 * - $var$
	 * - �var�
	 * - #var#
	 * - {row}
	 * - %func()%
	 * - �func()�
	 */
	function parse_col_content( $content )
	{
		// Make variable substitution for STRINGS:
		$content = preg_replace( '#\$ (\w+) \$#ix', "'.format_to_output(\$row->$1).'", $content );
		// Make variable substitution for URL STRINGS:
		$content = preg_replace( '#\� (\w+) \�#ix', "'.format_to_output(\$row->$1, 'urlencoded').'", $content );
		// Make variable substitution for escaped strings:
		$content = preg_replace( '#� (\w+) �#ix', "'.htmlentities(\$row->$1).'", $content );
		// Make variable substitution for RAWS:
		$content = preg_replace( '!\# (\w+) \#!ix', "\$row->$1", $content );
		// Make variable substitution for full ROW:
		$content = str_replace( '{row}', '$row', $content );
		// Make callback function substitution:
		$content = preg_replace( '#% (.+?) %#ix', "'.$1.'", $content );
		// Sometimes we need embedded function call, so we provide a second sign:
		$content = preg_replace( '#� (.+?) �#ix', "'.$1.'", $content );
		// Make variable substitution for intanciated Object:
		$content = str_replace( '{Obj}', "\$this->current_Obj", $content );
		// Make callback for Object method substitution:
		$content = preg_replace( '#@ (.+?) @#ix', "'.\$this->current_Obj->$1.'", $content );

		return $content;
	}


	/**
	 * Widget callback for template vars.
	 *
	 * This allows to replace template vars, see {@link Widget::replace_callback()}.
	 *
	 * @return string
	 */
	function replace_callback( $matches )
	{
		//echo $matches[1];
		switch( $matches[1] )
		{
			case 'start' :
				//total number of rows in the sql query
				return  ( ($this->page-1)*$this->limit+1 );

			case 'end' :
				return ( min( $this->total_rows, $this->page*$this->limit ) );

			case 'total_rows' :
				return ( $this->total_rows );

			case 'page' :
				//current page number
				return ( $this->page );

			case 'total_pages' :
				//total number of pages
				return ( $this->total_pages );

			case 'prev' :
				//inits the link to previous page
				return ( $this->page>1 )
					? '<a href="'.regenerate_url( $this->page_param, $this->page_param.'='.($this->page-1) ).'">'.$this->params['prev_text'].'</a>'
					: $this->params['prev_text'];

			case 'next' :
				//inits the link to next page
				return ( $this->page<$this->total_pages )
					? '<a href="'.regenerate_url( $this->page_param, $this->page_param.'='.($this->page+1) ).'">  '.$this->params['next_text'].'</a>'
					: $this->params['next_text'];

			case 'list' :
				//inits the page list
				return $this->page_list($this->first(),$this->last());

			case 'scroll_list' :
				//inits the scrolling list of pages
				return $this->page_scroll_list();

			case 'first' :
				//inits the link to first page
				return $this->display_first();

			case 'last' :
				//inits the link to last page
				return $this->display_last();

			case 'list_prev' :
				//inits the link to previous page range
				return $this->display_prev();

			case 'list_next' :
				//inits the link to next page range
				return $this->display_next();

			case 'nb_cols' :
				// Number of columns in result:
				return count($this->cols);

			default :
				return parent::replace_callback( $matches );
		}
	}


	/**
	 * Returns the first page number to be displayed in the list
	 */
	function first()
	{
		if( $this->page <= intval( $this->params['list_span']/2 ))
		{ // the current page number is small
			return 1;
		}
		elseif( $this->page > $this->total_pages-intval( $this->params['list_span']/2 ))
		{ // the current page number is big
			return max( 1, $this->total_pages-$this->params['list_span']+1);
		}
		else
		{ // the current page number can be centered
			return $this->page - intval($this->params['list_span']/2);
		}
	}


	/**
	 * returns the last page number to be displayed in the list
	 */
	function last()
	{
		if( $this->page > $this->total_pages-intval( $this->params['list_span']/2 ))
		{ //the current page number is big
			return $this->total_pages;
		}
		else
		{
			return min( $this->total_pages, $this->first()+$this->params['list_span']-1 );
		}
	}


	/**
	 * returns the link to the first page, if necessary
	 */
	function display_first()
	{
		if( $this->first() > 1 )
		{ //the list doesn't contain the first page
			return '<a href="'.regenerate_url( $this->page_param, $this->page_param.'=1' ).'">1</a>';
		}
		else
		{ //the list already contains the first page
			return NULL;
		}
	}


	/**
	 * returns the link to the last page, if necessary
	 */
	function display_last()
	{
		if( $this->last() < $this->total_pages )
		{ //the list doesn't contain the last page
			return '<a href="'.regenerate_url( $this->page_param, $this->page_param.'='.$this->total_pages ).'">'.$this->total_pages.'</a>';
		}
		else
		{ //the list already contains the last page
			return NULL;
		}
	}


	/**
	 * returns a link to previous pages, if necessary
	 */
	function display_prev()
	{
		if( $this->display_first() != NULL )
		{ //the list has to be displayed
			return '<a href="'.regenerate_url( $this->page_param, $this->page_param.'='.($this->first()-1) ).'">'
								.$this->params['list_prev_text'].'</a>';
		}

	}


	/**
	 * returns a link to next pages, if necessary
	 */
	function display_next()
	{
		if( $this->display_last() != NULL )
		{ //the list has to be displayed
			return '<a href="'.regenerate_url( $this->page_param,$this->page_param.'='.($this->last()+1) ).'">'
								.$this->params['list_next_text'].'</a>';
		}
	}


	/**
	 * Returns the page link list under the table
	 */
	function page_list($min, $max)
	{
		$i = 0;
		$list = '';

		for( $i=$min; $i<=$max; $i++)
		{
			if( $i == $this->page )
			{ //no link for the current page
				$list = $list.'<strong class="current_page">'.$i.'</strong> ';
			}
			else
			{ //a link for non-current pages
				$list = $list.'<a href="'.regenerate_url( $this->page_param, $this->page_param.'='.$i).'">'.$i.'</a> ';
			}
		}
		return $list;
	}


	/*
	 * Returns a scrolling page list under the table
	 */
	function page_scroll_list()
	{
		$scroll = '';
		$i = 0;
		$range = $this->params['scroll_list_range'];
		$min = 1;
		$max = 1;
		$option = '';
		$selected = '';
		$range_display='';

		if( $range > $this->total_pages )
			{ //the range is greater than the total number of pages, the list goes up to the number of pages
				$max = $this->total_pages;
			}
			else
			{ //initialisation of the range
				$max = $range;
			}

		//initialization of the form
		$scroll ='<form class="inline" method="post" action="'.regenerate_url( $this->page_param ).'">
							<select name="'.$this->page_param.'" onchange="parentNode.submit()">';//javascript to change page clicking in the scroll list

		while( $max <= $this->total_pages )
		{ //construction loop
			if( $this->page <= $max && $this->page >= $min )
			{ //display all the pages belonging to the range where the current page is located
				for( $i = $min ; $i <= $max ; $i++)
				{ //construction of the <option> tags
					$selected = ($i == $this->page) ? ' selected' : '';//the "selected" option is applied to the current page
					$option = '<option'.$selected.' value="'.$i.'">'.$i.'</option>';
					$scroll = $scroll.$option;
				}
			}
			else
			{ //inits the ranges inside the list
				$range_display = '<option value="'.$min.'">'
					.T_('Pages').' '.$min.' '. /* TRANS: Pages x _to_ y */ T_('to').' '.$max;
				$scroll = $scroll.$range_display;
			}

			if( $max+$range > $this->total_pages && $max != $this->total_pages)
			{ //$max has to be the total number of pages
				$max = $this->total_pages;
			}
			else
			{
				$max = $max+$range;//incrementation of the maximum value by the range
			}

			$min = $min+$range;//incrementation of the minimum value by the range


		}
		/*$input ='';
			$input = '<input type="submit" value="submit" />';*/
		$scroll = $scroll.'</select>'./*$input.*/'</form>';//end of the form*/

		return $scroll;
	}


	/**
	 * Get number of rows available for display
	 *
	 * {@internal DataObjectList::get_num_rows(-) }}
	 *
	 * @return integer
	 */
	function get_num_rows()
	{
		return $this->result_num_rows;
	}


	/**
	 * Template function: display message if list is empty
	 *
	 * {@internal DataObjectList::display_if_empty(-) }}
	 *
	 * @param string String to display if list is empty
   * @return true if empty
	 */
	function display_if_empty( $message = '' )
	{
		if( empty($message) )
		{ // Default message:
			$message = T_('Sorry, there is nothing to display...');
		}

		if( $this->result_num_rows == 0 )
		{
			echo $message;
			return true;
		}
		return false;
	}

}


/*
 * $Log$
 * Revision 1.44  2005/12/19 16:42:03  fplanque
 * minor
 *
 * Revision 1.43  2005/12/12 19:21:23  fplanque
 * big merge; lots of small mods; hope I didn't make to many mistakes :]
 *
 * Revision 1.40  2005/11/23 23:29:16  blueyed
 * Sorry, encoding messed up.
 *
 * Revision 1.39  2005/11/23 22:48:50  blueyed
 * minor (translation strings)
 *
 * Revision 1.38  2005/11/21 20:37:39  fplanque
 * Finished RSS skins; turned old call files into stubs.
 *
 * Revision 1.37  2005/11/18 21:01:21  fplanque
 * no message
 *
 * Revision 1.36  2005/11/17 16:46:08  fplanque
 * no message
 *
 * Revision 1.35  2005/11/07 02:13:22  blueyed
 * Cleaned up Sessions and extended Widget etc
 *
 * Revision 1.34  2005/10/28 20:08:46  blueyed
 * Normalized AdminUI
 *
 * Revision 1.33  2005/10/14 21:00:08  fplanque
 * Stats & antispam have obviously been modified with ZERO testing.
 * Fixed a sh**load of bugs...
 *
 * Revision 1.32  2005/10/12 18:24:37  fplanque
 * bugfixes
 *
 * Revision 1.31  2005/10/11 18:31:11  fplanque
 * no message
 *
 * Revision 1.30  2005/09/06 17:13:55  fplanque
 * stop processing early if referer spam has been detected
 *
 * Revision 1.29  2005/08/04 13:25:16  fplanque
 * fixed bug when there was no limit
 *
 * Revision 1.28  2005/07/15 18:12:01  fplanque
 * option to preload results objects to cache
 *
 * Revision 1.27  2005/06/27 23:59:25  blueyed
 * display(): fixes parse error for selecting straight value, supports "x AS y" selects
 *
 * Revision 1.25  2005/06/02 18:50:53  fplanque
 * no message
 *
 * Revision 1.24  2005/05/24 15:26:53  fplanque
 * cleanup
 *
 * Revision 1.23  2005/05/09 19:07:04  fplanque
 * bugfixes + global access permission
 *
 * Revision 1.22  2005/05/03 14:43:33  fplanque
 * no message
 *
 * Revision 1.21  2005/05/03 14:38:15  fplanque
 * finished multipage userlist
 *
 * Revision 1.20  2005/05/02 19:06:47  fplanque
 * started paging of user list..
 *
 * Revision 1.19  2005/04/07 17:55:50  fplanque
 * minor changes
 *
 * Revision 1.18  2005/04/06 19:11:02  fplanque
 * refactored Results class:
 * all col params are now passed through a 2 dimensional table which allows easier parametering of large tables with optional columns
 *
 * Revision 1.17  2005/03/21 17:38:01  fplanque
 * results/table layout refactoring
 *
 * Revision 1.16  2005/03/02 15:37:59  fplanque
 * experimentoing better count() automation :/
 *
 * Revision 1.15  2005/02/28 09:06:33  blueyed
 * removed constants for DB config (allows to override it from _config_TEST.php), introduced EVO_CONFIG_LOADED
 *
 * Revision 1.14  2005/02/27 20:28:03  blueyed
 * taken count() out of loop
 *
 * Revision 1.13  2005/02/17 19:36:24  fplanque
 * no message
 *
 * Revision 1.12  2005/01/28 19:28:03  fplanque
 * enhanced UI widgets
 *
 * Revision 1.11  2005/01/26 16:47:13  fplanque
 * i18n tuning
 *
 * Revision 1.10  2005/01/20 19:19:34  fplanque
 * bugfix
 *
 * Revision 1.9  2005/01/20 18:45:54  fplanque
 * cleanup
 *
 * Revision 1.8  2005/01/13 19:53:50  fplanque
 * Refactoring... mostly by Fabrice... not fully checked :/
 *
 * Revision 1.7  2005/01/12 20:40:40  fplanque
 * no message
 *
 * Revision 1.6  2005/01/03 15:17:52  fplanque
 * no message
 *
 * Revision 1.5  2004/12/27 18:37:58  fplanque
 * changed class inheritence
 *
 * Moved stuff down from DataObjectList class
 *
 * Revision 1.3  2004/12/17 20:39:48  fplanque
 * added sort orders and extended navigation
 *
 * Revision 1.2  2004/12/13 21:29:58  fplanque
 * refactoring
 *
 * Revision 1.1  2004/10/13 22:46:32  fplanque
 * renamed [b2]evocore/*
 *
 * Revision 1.4  2004/10/12 17:22:29  fplanque
 * Edited code documentation.
 *
 */
?>