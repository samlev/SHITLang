Sam's Horribly Inept Template Language
=========

A.K.A. SHIT Lang
---------
What is it? Well it's a super simple templating engine. Like really simple. Like it's pretty much the worst one ever.

You probably shouldn't use it.

Why did you build it?
---------
I built the first version for [Blogfile](https://github.com/samlev/blogfile). I upgraded it a little, but not much.

What does it do?
---------
Not much. It lets you define templates, which allow you to add content through slots, "require" other templates, and handles template aquisition. If you're looking for conditionals, loops, etc. then you'll be pretty disappointed.

How do I use it?
---------
A sample template file (named, for example, 'main.tpl') might look something like this:

    <%%STARTTEMPLATE MAIN_HTML%%>
    <!DOCTYPE html>
    <html>
    <head>
      <title>SHIT Lang template - <%%OPENSLOT PAGE_TITLE%%></title>
    </head>
    <%%REQUIRETEMPLATE BODY%%>
    </html>
    <%%ENDTEMPLATE MAIN_HTML%%>
    
    <%%STARTTEMPLATE BODY%%>
    <body>
      <div id="body-content">
        <h1><%%OPENSLOT PAGE_TITLE%%></h1>
        <%%OPENSLOT PAGE_BODY%%>
      </div>
    </body>
    <%%ENDTEMPLATE BODY%%>

Then you would load and use the templates like this:

    $tpl = new Tpl\Engine();
    $tpl->parseTemplates(file_get_contents('main.tpl');
    echo $tpl->render('MAIN_HTML', array('PAGE_TITLE' => 'Foo',
                                         'PAGE_BODY' => 'Lorem Ipsum or something.'));

The template engine will parse the defined templates, make a list of required templates, and possible content slots. It will aquire required templates from the engine if they exist, and data will be passed down to sub-templates. The final output of this would be:

    <!DOCTYPE html>
    <html>
    <head>
      <title>SHIT Lang template - Foo</title>
    </head>
    <body>
      <div id="body-content">
        <h1>Foo</h1>
        Lorem Ipsum or something.
      </div>
    </body>
    </html>

What is this useful for?
---------
Very basic templates, I guess? It might be useful for email templates or something. I don't know. If you find a use for it, then good on you!

What licence is it under?
---------
I'm going to put it out there under the ~~[Do what the fuck you want to public licence](http://www.wtfpl.net/)~~ [Do I Look Like I Give A Shit Public Licence](https://github.com/samlev/DILLIGASPL). Because I don't really care. You probably shouldn't be using this anywhere.
