<?php

namespace app\index\controller;

use think\Controller;

class Zgyf extends Controller
{

    /**
     * 采集文章getArticle
     * 验房案例 136 138 => 1
     * 验房标准 3       => 2
     * 验房法规 4       => 3
     * 验房百科 5       => 4
     *
     * @return void
     */
    public function getArticle()
    {
        set_time_limit(0);
        header("Content-Type: text/html;charset=utf-8");
        include_once VENDOR_PATH . 'phpQuery/phpQuery.php';

        $cat = [
            136 => 1,
            138 => 1,
            3 => 2,
            4 => 3,
            5 => 4
        ];

        $exist_ids = model('house_inspection')->column('oid');  //已经采集的文章id

        $tag = 0;   //标签

        // 遍历栏目
        foreach ($cat as $ocatid => $ncatid) {

            $url = 'http://www.yanfang12365.com/list/?' . $ocatid . '_1.html';
            $did = \phpQuery::newDocumentFile($url);
            $suburl = parse_url($url);
            $baseurl = $suburl['scheme'] . '://' . $suburl['host'];
            $artlist = pq(".content_list", $did)->find('li');
            $i = 0;
            $data = [];
            foreach ($artlist as $li) {
                $data[$i]['title'] = pq($li)->find('a:eq(1)')->text();
                $data[$i]['url'] = $baseurl . pq($li)->find('a:eq(1)')->attr('href');
                $i++;
            }
            // dump($data);

            // 遍历文章
            $save = [];
            $j = 0;
            foreach ($data as $key => $value) {
                // http://www.yanfang12365.com/content/?3648.html
                preg_match('/\?(\d+)\.html/', $value['url'], $match);
                if (empty($match[1])) continue;
                $thisid = $match[1];
                if (in_array($thisid, $exist_ids)) {
                    continue; //去重
                }
                $save[$j]['oid'] = $thisid;
                $save[$j]['catid'] = $ncatid;  //栏目id
                $save[$j]['title'] = $value['title'];   //标题
                $did = \phpQuery::newDocumentFile($value['url']);
                // [DCIC]  [住建部]
                preg_match('/\[(\w+|\W+)\]/', mb_convert_encoding(pq(".right", $did)->find('span:eq(2)'), 'utf-8', 'GBK'), $source);
                $save[$j]['source'] = empty($source[1]) ? '' : $source[1];
                preg_match('/\[(\w+|\W+)\]/', mb_convert_encoding(pq(".right", $did)->find('span:eq(1)'), 'utf-8', 'GBK'), $author);
                $save[$j]['author'] = empty($author[1]) ? '' : $author[1];
                // $data = pq(".right", $did)->find('span:eq(3)');
                $con = file_get_contents($value['url']);
                $con = mb_convert_encoding($con, 'utf-8', 'GBK');
                $preg = '/<div class=\"right\"(.*?)>(.*?)<\/div>(.*?)<div class="cl">/ism';
                preg_match_all($preg, $con, $res);
                $preg = '/<span style="color:#ff0000".*?<\/p>(\n|\s)*<span>(.*?)<\/span>(\n|\s)*<DIV class=pageNavi>/ism';
                preg_match_all($preg, $res[0][0], $content);
                $data = $content[2][0]; //未过滤广告
                $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="92578"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
                $data = preg_replace('/<section style="margin: 0px; padding: 0px; max-width: 100%; box-sizing: border-box; max-inline-size: 100%; display: flex; justify-content: center; align-items: center; word-wrap: break-word !important; overflow-wrap: break-word !important; outline: none 0px !important;"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
                $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="93224"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
                $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="93539"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
                $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="93541"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
                $data = preg_replace('/<blockquote[^>]*[^>]*>[\s\S]*<\/blockquote>/i', '', $data);
                $data = preg_replace('/荣誉与资质/i', '', $data);
                $data = preg_replace('/预约验房专享：400-1868-978/i', '', $data);
                $data = preg_replace('/官网：www.yanfang12365.com/i', '', $data);
                $data = preg_replace('/DCIC深度中国验房全国城市公司：/i', '', $data);
                $data = preg_replace('/深圳/i', '', $data);
                $data = preg_replace('/中山\|佛山\|惠州\|江门\|厦门/i', '', $data);
                $data = preg_replace('/\|成都\|珠海\|东莞\|汕头\|梅州\|河源\|海口\|遵义\|重庆\|贵阳\|新疆\|泸州\|台州\|常州\|攀枝花\|绵阳\|凉山州\|青岛\|温州\|无锡\|潍坊\|绍兴\|宁波\|苏州\|泉州\|赣州\|南宁\|长沙\|广州\|肇庆\|南昌\|昆明\|郴州\|福州\|贵阳\|萍乡\|临沂\|澳门\|香港/i', '', $data);
                $data = preg_replace('/\|/i', '', $data);
                $pattern = "/<img.*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.png]))[\'|\"].*?[\/]?>/i";
                preg_match_all($pattern, $data, $matchContent);
                if (isset($matchContent[1][1])) {
                    $pattern = "/\/upLoad\/news\//i";
                    preg_match_all($pattern, $matchContent[1][1], $img);
                    if(isset($img[1][0])){
                        $save[$j]['thumb'] = '/image/empyt.png';
                    }else{
                        $save[$j]['thumb'] = $matchContent[1][1];   //缩略图
                    }
                } else {
                    $save[$j]['thumb'] = '/image/empyt.png';
                }

                $admin = request()->admin;
                $save[$j]['real_name'] = $admin['real_name'] ?? "";
                $save[$j]['admin_id'] = $this->request->admin_id ?? "";   //负责人
                $save[$j]['create_time'] = $save[$j]['update_time'] = $save[$j]['input_time'] = date('Y-m-d H:i:s'); //创建时间（文章中没有发布时间）
                $save[$j]['content'] = $data;   //内容
                if($j == 4) break;
                $j++;
                echo $url . 'save';
            }

            
            if (!empty($save)) {
                $tag = model('house_inspection')->max('id');
                if (empty($tag)) $tag = 0;
                model('house_inspection')->allowField(true)->saveAll($save);

                $rels = model('house_inspection')->where('id', '>', $tag)->column('oid', 'id');

                // dump($rels);
                $contents = [];
                foreach ($save as $key => $value) {
                    $contents[$value['oid']] = $value['content'];
                }
                // echo $ocatid . 'contents';
                // dump($contents);

                $savecontents = [];
                $i = 0;
                foreach ($rels as $nid => $oid) {
                    $savecontents[$i]['id'] = $nid;
                    $savecontents[$i]['content'] = $contents[$oid];
                    $i++;
                }
                model('house_inspection_data')->insertAll($savecontents);
                echo $url  . 'savecontents';         
            } else {
                echo $url . ' 没有更新</br>';
            }
            
        }
    }

    public function test()
    {
        $id = input('id/s');
        header("Content-Type: text/html;charset=utf-8");
        include_once VENDOR_PATH . 'phpQuery/phpQuery.php';
        // http://www.yanfang12365.com/content/?6321.html 分割线带图
        // http://www.yanfang12365.com/content/?6304.html 分割线不带图
        // http://www.yanfang12365.com/content/?6160.html 文章内容中出现要删特征
        $url = 'http://www.yanfang12365.com/content/?' . $id . '.html';
        $did = \phpQuery::newDocumentFile($url);
        // [DCIC]  [住建部]
        preg_match('/\[(\w+|\W+)\]/', mb_convert_encoding(pq(".right", $did)->find('span:eq(2)'), 'utf-8', 'GBK'), $source);
        $save['source'] = empty($source[1]) ? '' : $source[1];
        preg_match('/\[(\w+|\W+)\]/', mb_convert_encoding(pq(".right", $did)->find('span:eq(1)'), 'utf-8', 'GBK'), $author);
        $save['author'] = empty($author[1]) ? '' : $author[1];
        // $data = pq(".right", $did)->find('span:eq(3)');
        $con = file_get_contents($url);
        $con = mb_convert_encoding($con, 'utf-8', 'GBK');
        $preg = '/<div class=\"right\"(.*?)>(.*?)<\/div>(.*?)<div class="cl">/ism';
        preg_match_all($preg, $con, $res);
        $preg = '/<span style="color:#ff0000".*?<\/p>(\n|\s)*<span>(.*?)<\/span>(\n|\s)*<DIV class=pageNavi>/ism';
        preg_match_all($preg, $res[0][0], $content);
        $data = $content[2][0]; //获取未过滤友商信息
        $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="92578"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
        $data = preg_replace('/<section style="margin: 0px; padding: 0px; max-width: 100%; box-sizing: border-box; max-inline-size: 100%; display: flex; justify-content: center; align-items: center; word-wrap: break-word !important; overflow-wrap: break-word !important; outline: none 0px !important;"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
        $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="93224"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
        $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="93539"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
        $data = preg_replace('/<section class="_135editor" data-tools="135编辑器" data-id="93541"[^>]*[^>]*>[\s\S]*<\/section>/i', '', $data);
        $data = preg_replace('/<blockquote[^>]*[^>]*>[\s\S]*<\/blockquote>/i', '', $data);
        $data = preg_replace('/荣誉与资质/i', '', $data);
        $data = preg_replace('/预约验房专享：400-1868-978/i', '', $data);
        $data = preg_replace('/官网：www.yanfang12365.com/i', '', $data);
        $data = preg_replace('/DCIC深度中国验房全国城市公司：/i', '', $data);
        $data = preg_replace('/深圳/i', '', $data);
        $data = preg_replace('/中山\|佛山\|惠州\|江门\|厦门/i', '', $data);
        $data = preg_replace('/\|成都\|珠海\|东莞\|汕头\|梅州\|河源\|海口\|遵义\|重庆\|贵阳\|新疆\|泸州\|台州\|常州\|攀枝花\|绵阳\|凉山州\|青岛\|温州\|无锡\|潍坊\|绍兴\|宁波\|苏州\|泉州\|赣州\|南宁\|长沙\|广州\|肇庆\|南昌\|昆明\|郴州\|福州\|贵阳\|萍乡\|临沂\|澳门\|香港/i', '', $data);
        $data = preg_replace('/\|/i', '', $data);
        echo $data;

        // dump($data);

    }

}
