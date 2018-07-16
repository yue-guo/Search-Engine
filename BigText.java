package hw5;

import java.io.BufferedWriter;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileWriter;
import java.io.IOException;

import org.apache.tika.exception.TikaException;
import org.apache.tika.metadata.Metadata;
import org.apache.tika.parser.ParseContext;
import org.apache.tika.parser.html.HtmlParser;
import org.apache.tika.sax.BodyContentHandler;
import org.xml.sax.SAXException;

public class BigText {
     public static void main( String[] args ) throws IOException, SAXException, TikaException{
        
            String outputfilepath = "/Users/YueGuo/Sites/big.txt";
            String dirPath = "/Users/YueGuo/Desktop/csci572/hw4/BG/BG/";
            
            File outfile = new File(outputfilepath);
            File dir = new File(dirPath);
            FileWriter ff = new FileWriter(outputfilepath);
            BufferedWriter bb = new BufferedWriter(ff);

            for(File f : dir.listFiles()){
                //Tika default
                BodyContentHandler bcHandler = new BodyContentHandler(1024 * 1024 * 10);
                Metadata metadata = new Metadata();
                FileInputStream fInputStream = new FileInputStream(f);
                ParseContext pContext = new ParseContext();
                
                //parse html
                HtmlParser msofficeparser = new HtmlParser();
                msofficeparser.parse(fInputStream, bcHandler, metadata, pContext);
                //bb.write(bcHandler.toString());
                bb.write(bcHandler.toString().replaceAll("\\s+", " "));

            }
            
            bb.flush();
            bb.close();
            System.out.println("done");
        }

}
