<?php namespace Pyro\Module\Search\Model;

use Illuminate\Database\Eloquent\Model;

/**
 * Search Index model
 *
 * @author		PyroCMS Dev Team
 * @package		PyroCMS\Core\Modules\Search\Models
 * @copyright   Copyright (c) 2012, PyroCMS LLC
 */
class Search extends Model
{

	/**
     * Define the table name
     *
     * @var string
     */
    protected $table = 'search_index';

    /**
     * The attributes that aren't mass assignable
     *
     * @var array
     */
    protected $guarded = array();

    /**
     * Disable updated_at and created_at on table
     *
     * @var boolean
     */
    public $timestamps = false;

	/**
	 * Index
	 *
	 * Store an entry in the search index.
	 *
	 * <code>
	 * Search::index(
     *     'blog',
     *     'blog:post',
     *     'blog:posts',
     *     $id,
     *     'blog/'.date('Y/m/', $post->created_on).$post->slug,
     *     $post->title,
     *     $post->intro,
     *     array(
     *         'cp_edit_uri'    => 'admin/blog/edit/'.$id,
     *         'cp_delete_uri'  => 'admin/blog/delete/'.$id,
     *         'keywords'       => $post->keywords,
     *     )
     * );
     * </code>
	 *
	 * @param	string	$module		The module that owns this entry
	 * @param	string	$singular	The unique singular language key for this piece of data
	 * @param	string	$plural		The unique plural language key that describes many pieces of this data
	 * @param	int 	$entry_id	The id for this entry
	 * @param	string 	$uri		The relative uri to installation root
	 * @param	string 	$title		Title or Name of this entry
	 * @param	string 	$description Description of this entry
	 * @param	array 	$options	Options such as keywords (array or string - hash of keywords) and cp_edit_url/cp_delete_url
	 * @return	array
	 */
	public static function index($module, $singular, $plural, $entry_id, $uri, $title, $description = null, array $options = array()){
		// Drop it so we can create a new index
		$this->drop_index($module, $singular, $entry_id);

		$insert_data = array();

		// Hand over keywords without needing to look them up
		if ( ! empty($options['keywords'])) {
			if (is_array($options['keywords'])) {
				$insert_data['keywords'] = impode(',', $options['keywords']);

			} elseif (is_string($options['keywords'])) {
				$insert_data['keywords'] = Keywords::get_string($options['keywords']);
				$insert_data['keyword_hash'] = $options['keywords'];
			}
		}

		// Store a link to edit this entry
		if ( ! empty($options['cp_edit_uri'])) {
			$insert_data['cp_edit_uri'] = $options['cp_edit_uri'];
		}

		// Store a link to delete this entry
		if ( ! empty($options['cp_delete_uri'])) {
			$insert_data['cp_delete_uri'] = $options['cp_delete_uri'];
		}

		$insert_data['title'] 			= $title;
		$insert_data['description'] 	= strip_tags($description);
		$insert_data['module'] 			= $module;
		$insert_data['entry_key'] 		= $singular;
		$insert_data['entry_plural'] 	= $plural;
		$insert_data['entry_id'] 		= $entry_id;
		$insert_data['uri'] 			= $uri;

		return self::insertGetId(
			$insert_data
		);
	}

	/**
	 * Drop index
	 *
	 * Delete an index for an entry
	 *
	 * <code>
	 * Search::drop_index('blog', 'blog:post', $id);
	 * </code>
	 *
	 * @param	string	$module		The module that owns this entry
	 * @param	string	$singular	The unique singular "key" for this piece of data
	 * @param	int 	$entry_id	The id for this entry
	 * @return	array
	 */
	public static function drop_index($module, $singular, $entry_id){
		return self::where('module', '=', $module)
			->where('entry_key', '=', $singular)
			->where('entry_id', '=', $entry_id)
			->delete();
	}

	/**
	 * Filter
	 *
	 * Breaks down a search result by module and entity
	 *
	 * @param	array	$filter	Modules will be the key and the values are entity_plural (string or array)
	 * @return	array
	 */
	public static function filter($filter){
		// Filter Logic
		if (! $filter){
			return $this;
		}

		self::orwhere(function($mainQuery){
			foreach ($filter as $module => $plural){
				$mainQuery->orwhere(function($subQuery){
					$subQuery->where('module', '=', $module);
					$subQuery->whereIn('entry_plural', (array) $plural);
				});
			}
		});

		return $this;
	}

	/**
	 * Count
	 *
	 * Count relevant search results for a specific term
	 *
	 * @param	string	$query	Query or terms to search for
	 * @return	array
	 */
	public static function count($query)
	{
		return self::where(
			DB::raw('MATCH(title, description, keywords) AGAINST ("*'.$query.'*" IN BOOLEAN MODE) > 0')
		)->count();		
	}

	/**
	 * Search
	 *
	 * Delete an index for an entry
	 *
	 * @param	string	$query	Query or terms to search for
	 * @return	array
	 */
	public static function search($query, $limit = 8, $offset = 0)
	{
		return self::select('title, description, keywords, module, entry_key, entry_plural, uri, cp_edit_uri'))
			->select(DB::raw('MATCH(title, description, keywords) AGAINST ("*'.$query.'*" IN BOOLEAN MODE) as bool_relevance'))
			->select(DB::raw('MATCH(title, description, keywords) AGAINST ("*'.$query.'*") AS relevance'))
			->having('bool_relevance', '>', 0)
			->orderBy('relevance', 'desc')
			->skip($offset)
			->take($limit)
			->get();
	}
}
