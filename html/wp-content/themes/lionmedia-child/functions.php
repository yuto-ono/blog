<?php
//////////////////////////////////////////////////
//親テーマのCSSを読み込む
//////////////////////////////////////////////////
function fit_head_child() {
	if ( get_option('fit_seo_cssLoad') == "value2" && get_option('fit_seo_cssLoad-main')) {
		echo '<link class="css-async" rel href="'.get_template_directory_uri().'/style.css">'."\n";
	}else{
		echo '<link rel="stylesheet" href="'.get_template_directory_uri().'/style.css">'."\n";
	}
	if (is_singular()){
		if ( get_option('fit_seo_cssLoad') == "value2" && get_option('fit_seo_cssLoad-content')) {
			echo '<link class="css-async" rel href="'.get_template_directory_uri().'/css/content.css">'."\n";
		}else{
			echo '<link rel="stylesheet" href="'.get_template_directory_uri().'/css/content.css">'."\n";
		}
	}
}
add_action('wp_head', 'fit_head_child');



//////////////////////////////////////////////////
//下記ユーザーカスタマイズエリア
//////////////////////////////////////////////////


// 親テーマの functions.php が実行された後に呼ばれる
function after_parent_setup() {
	add_filter('the_content', 'add_custom_outline');
	remove_filter('the_content', 'add_outline');
}
add_action('after_setup_theme', 'after_parent_setup');


//////////////////////////////////////////////////
//オリジナル目次を作成
//////////////////////////////////////////////////
function get_custom_outline_info($content) {
	// 目次のHTMLを入れる変数を定義します。
	$outline = '';
	// h1〜h6タグの個数を入れる変数を定義します。
	$counter = 0;
    // 記事内のh1〜h6タグを検索します。(idやclass属性も含むように改良)
    if (preg_match_all('/<h(2)[^>]*>(.*?)<\/h\1>/', $content, $matches,  PREG_SET_ORDER)) {
    	   // 記事内で使われているh1〜h6タグの中の、1〜6の中の一番小さな数字を取得します。
    	   // ※以降ソースの中にある、levelという単語は1〜6のことを表します。
        $min_level = min(array_map(function($m) { return $m[1]; }, $matches));
        // スタート時のlevelを決定します。
        // ※このレベルが上がる毎に、<ul></li>タグが追加されていきます。
        $current_level = $min_level - 1;
        // 各レベルの出現数を格納する配列を定義します。
        $sub_levels = array('1' => 0, '2' => 0, '3' => 0, '4' => 0, '5' => 0, '6' => 0);
        // 記事内で見つかった、hタグの数だけループします。
        foreach ($matches as $m) {
            $level = $m[1];  // 見つかったhタグのlevelを取得します。
            $text = $m[2];  // 見つかったhタグの、タグの中身を取得します。
            // li, ulタグを閉じる処理です。2ループ目以降に中に入る可能性があります。
            // 例えば、前回処理したのがh3タグで、今回出現したのがh2タグの場合、
            // h3タグ用のulを閉じて、h2タグに備えます。
            while ($current_level > $level) {
                $current_level--;
                $outline .= '</li></ul>';
            }
            // 同じlevelの場合、liタグを閉じ、新しく開きます。
            if ($current_level == $level) {
                $outline .= '</li><li class="outline__item">';
            } else {
                // 同じlevelでない場合は、ul, liタグを追加していきます。
                // 例えば、前回処理したのがh2タグで、今回出現したのがh3タグの場合、
                // h3タグのためにulを追加します。
                while ($current_level < $level) {
                    $current_level++;
                    $outline .= sprintf('<ul class="outline__list outline__list-%s"><li class="outline__item">', $current_level);
                }
                // 見出しのレベルが変わった場合は、現在のレベル以下の出現回数をリセットします。
                for ($idx = $current_level + 0; $idx < count($sub_levels); $idx++) {
                    $sub_levels[$idx] = 0;
                }
            }
            // 各レベルの出現数を格納する配列を更新します。
            $sub_levels[$current_level]++;
            // 現在処理中のhタグの、パスを入れる配列を定義します。
            // 例えば、h2 -> h3 -> h3タグと進んでいる場合は、
            // level_fullpathはarray(1, 2)のようになります。
            // ※level_fullpath[0]の1は、1番目のh2タグの直下に入っていることを表します。
            // ※level_fullpath[1]の2は、2番目のh3を表します。
            $level_fullpath = array();
            for ($idx = $min_level; $idx <= $level; $idx++) {
                $level_fullpath[] = $sub_levels[$idx];
            }
            $target_anchor = 'outline__' . implode('_', $level_fullpath);

            // 目次に、<a href="#outline_1_2">1.2 見出し</a>のような形式で見出しを追加します。
            $outline .= sprintf('<a class="outline__link" href="#%s"><span class="outline__number">%s.</span> %s</a>', $target_anchor, implode('.', $level_fullpath), strip_tags($text));
            // 本文中の見出し本体を、<h3>見出し</h3>を<h3 id="outline_1_2">見出し</h3>
            // のような形式で置き換えます。
            $hid = preg_replace('/<h([1-6])/', '<h\1 id="' .$target_anchor . '"', $m[0]);
            $content = str_replace($m[0], $hid, $content);
			
        }
        // hタグのループが終了後、閉じられていないulタグを閉じていきます。
        while ($current_level >= $min_level) {
            $outline .= '</li></ul>';
            $current_level--;
        }
        // h1〜h6タグの個数
        $counter = count($matches);
    }
    return array('content' => $content, 'outline' => $outline, 'count' => $counter);
}

//目次を作成します。
function add_custom_outline($content) {

    // 目次を表示するために必要な見出しの数
	if(get_option('fit_post_outline_number')){
		$number = get_option('fit_post_outline_number');
	}else{
		$number = 1;
	}
    // 目次関連の情報を取得します。
    $outline_info = get_custom_outline_info($content);
    $content = $outline_info['content'];
    $outline = $outline_info['outline'];
    $count = $outline_info['count'];
	if (get_option('fit_post_outline_close') ) {
		$close = "";
	}else{
		$close = "checked";
	}
    if ($outline != '' && $count >= $number) {
        // 目次を装飾します。
        $decorated_outline = sprintf('
		<div class="outline">
		  <span class="outline__title">目次</span>
		  <input class="outline__toggle" id="outline__toggle" type="checkbox" '.$close.'>
		  <label class="outline__switch" for="outline__toggle"></label>
		  %s
		</div>', $outline);
        // カスタマイザーで目次を非表示にする以外が選択された時＆個別非表示が1以外の時に目次を追加します。
        if ( is_single() || is_page('profile') ) {
            if ( get_option('fit_post_outline') != 'value2' && get_post_meta(get_the_ID(), 'outline_none', true) != '1') {
                $shortcode_outline = '[outline]';
                if (strpos($content, $shortcode_outline) !== false) {
                    // 記事内にショートコードがある場合、ショートコードを目次で置換します。
                    $content = str_replace($shortcode_outline, $decorated_outline, $content);
                } else if (preg_match('/<h[1-6].*>/', $content, $matches, PREG_OFFSET_CAPTURE)) {
                    // 最初のhタグの前に目次を追加します。
                    $pos = $matches[0][1];
                    $content = substr($content, 0, $pos) . $decorated_outline . substr($content, $pos);
                }
            }
        }
    }
	return $content;
}
