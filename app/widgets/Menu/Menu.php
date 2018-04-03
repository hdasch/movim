<?php

include_once WIDGETS_PATH.'Post/Post.php';

class Menu extends \Movim\Widget\Base
{
    private $_paging = 15;

    function load()
    {
        $this->registerEvent('post', 'onPost');
        $this->registerEvent('post_retract', 'onRetract', 'news');
        $this->registerEvent('pubsub_postdelete', 'onRetract', 'news');

        $this->addjs('menu.js');
    }

    function onRetract($packet)
    {
        $this->ajaxGetAll();
    }

    function onStream($count)
    {
        $view = $this->tpl();
        $view->assign('count', $count);
        $view->assign('refresh', $this->call('ajaxGetAll'));

        $this->rpc('movim_posts_unread', $count);
        $this->rpc('MovimTpl.fill', '#menu_refresh', $view->draw('_menu_refresh', true));
    }

    function onPost($packet)
    {
        $pd = new \Modl\PostnDAO;

        $since = \App\Cache::c('since');
        $count = $pd->getCountSince($since);
        $post = $packet->content;

        if (!is_object($post)) return;

        // We reload a fresh Post
        $post = $pd->get($post->origin, $post->node, $post->nodeid);

        if (is_object($post)
        && $post->isComment()
        && !$post->isMine()) {
            $contact = \App\Contact::firstOrNew(['id' => $post->aid]);
            Notification::append(
                'news',
                $contact->getTrueName(),
                $post->title,
                $contact->getPhoto('s'),
                2
            );
        } elseif ($count > 0
        && is_object($post)
        && (strtotime($post->published) > strtotime($since))) {
            if ($post->isMicroblog()) {
                $contact = \App\Contact::firstOrNew(['id' => $post->origin]);

                if ($post->title == null) {
                    $title = __('post.default_title');
                } else {
                    $title = $post->title;
                }

                if (!$post->isMine()) {
                    Notification::append(
                        'news',
                        $contact->getTrueName(),
                        $title,
                        $contact->getPhoto('s'),
                        2,
                        $this->route('post', [$post->origin, $post->node, $post->nodeid]),
                        $this->route('contact', $post->origin)
                    );
                }
            } else {
                $logo = ($post->logo) ? $post->getLogo() : null;

                Notification::append(
                    'news',
                    $post->title,
                    $post->node,
                    $logo,
                    2,
                    $this->route('post', [$post->origin, $post->node, $post->nodeid]),
                    $this->route('community', [$post->origin, $post->node])
                );
            }

            $this->onStream($count);
        }
    }

    function ajaxGetAll($page = 0)
    {
        $this->ajaxGet('all', null, null, $page);
    }

    function ajaxGetNews($page = 0)
    {
        $this->ajaxGet('news', null, null, $page);
    }

    function ajaxGetFeed($page = 0)
    {
        $this->ajaxGet('feed', null, null, $page);
    }

    function ajaxGetNode($server = null, $node = null, $page = 0)
    {
        $this->ajaxGet('node', $server, $node, $page);
    }

    function ajaxGetMe($page = 0)
    {
        $this->ajaxGet('me', null, null, $page);
    }

    function ajaxGet($type = 'all', $server = null, $node = null, $page = 0)
    {
        $html = $this->prepareList($type, $server, $node, $page);

        if ($page > 0) {
            $this->rpc('MovimTpl.append', '#menu_wrapper', $html);
        } else {
            $this->rpc('MovimTpl.fill', '#menu_widget', $html);
        }

        $this->rpc('MovimUtils.enhanceArticlesContent');
        $this->rpc('Menu.refresh');
    }

    function prepareList($type = 'all', $server = null, $node = null, $page = 0)
    {
        $view = $this->tpl();
        $pd = new \Modl\PostnDAO;
        $count = $pd->getCountSince(\App\Cache::c('since'));
        // getting newer, not older
        if ($page == 0 || $page == ""){
            $count = 0;
            \App\Cache::c('since', date(DATE_ISO8601, strtotime($pd->getLastDate())));
        }

        $next = $page + 1;

        switch ($type) {
            case 'all' :
                $items = \App\Post::whereIn('server', function($query) {
                    $query->from('rosters')
                          ->select('jid')
                          ->where('session_id', SESSION_ID)
                          ->where('subscription', 'both');
                })
                ->orWhereIn('id', function($query) {
                    $query->select('id')
                          ->from('posts')
                          ->where('node', 'urn:xmpp:microblog:0')
                          ->where('server', $this->user->id);
                })
                ->orWhereIn('id', function($query) {
                    $query->select('id')
                          ->from('posts')
                          ->whereIn('server', function($query) {
                            $query->select('server')
                                  ->from('subscriptions')
                                  ->where('jid', $this->user->id);
                          })
                          ->whereIn('node', function($query) {
                            $query->select('node')
                                  ->from('subscriptions')
                                  ->where('jid', $this->user->id);
                          });
                });
                $view->assign('history', $this->call('ajaxGetAll', $next));
                break;
            /*case 'news' :
                $view->assign('history', $this->call('ajaxGetNews', $next));
                $items  = $pd->getNews($page * $this->_paging + $count, $this->_paging);
                break;
            case 'feed' :
                $view->assign('history', $this->call('ajaxGetFeed', $next));
                $items  = $pd->getFeed($page * $this->_paging + $count, $this->_paging);
                break;
            case 'me' :
                $view->assign('jid', $this->user->getLogin());
                $view->assign('history', $this->call('ajaxGetMe', $next));
                $items  = $pd->getMe($page * $this->_paging + $count, $this->_paging);
                break;
            case 'node' :
                $view->assign('history', $this->call('ajaxGetNode', '"'.$server.'"', '"'.$node.'"', '"'.$next.'"'));
                $items  = $pd->getNode($server, $node, $page * $this->_paging + $count, $this->_paging);
                break;*/
        }

        $view->assign('items', $items
            ->orderBy('published', 'desc')
            ->skip($page * $this->_paging + $count)
            ->take($this->_paging)->get());
        $view->assign('type', $type);
        $view->assign('page', $page);
        $view->assign('paging', $this->_paging);

        $html = $view->draw('_menu_list', true);

        return $html;
    }

    function preparePost($p)
    {
        $pw = new \Post;
        return $pw->preparePost($p, true, false, true);
    }
}
